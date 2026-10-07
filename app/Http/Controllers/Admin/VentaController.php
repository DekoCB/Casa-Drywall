<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\CuentaBancaria;
use App\Models\MetodoPago;
use App\Models\MovimientoAlmacen;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\CodigoQrService;
use App\Services\GeneradorCorrelativo;
use App\Services\NumeroALetras;
use App\Services\PrecioCalculador;
use App\Services\Sunat\ApiGoEmisionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registro de comprobantes de venta (SUNAT).
 *
 * Migrado de `administrador/ventas.php`: cada venta es un comprobante con su
 * tipo, serie y correlativo, y un único tipo de operación (gravada, exonerada
 * o inafecta). No lleva líneas de producto.
 */
class VentaController extends Controller
{
    /**
     * Códigos de comprobante con su serie sugerida. `COT` (Cotización) y
     * `NV` (Nota de Venta) no son códigos SUNAT — son documentos internos
     * (una todavía no es una venta confirmada, la otra no necesita un
     * comprobante fiscal) y por eso nunca se registran en API-GO
     * (`crearComprobante()` solo reconoce los códigos numéricos).
     */
    public const TIPOS = [
        'COT' => ['nombre' => 'Cotización',           'serie' => 'CT01'],
        'NV' => ['nombre' => 'Nota de Venta',         'serie' => 'NV01'],
        '01' => ['nombre' => '01 — Factura',          'serie' => 'F001'],
        '03' => ['nombre' => '03 — Boleta de Venta',  'serie' => 'B001'],
        '07' => ['nombre' => '07 — Nota de Crédito',  'serie' => 'FC01'],
        '08' => ['nombre' => '08 — Nota de Débito',   'serie' => 'FD01'],
        '09' => ['nombre' => '09 — Liquidación',      'serie' => 'FL01'],
    ];

    /** Equivalente en Cobranzas (FT/BV/NC/OT) de cada código SUNAT de comprobante. */
    private const TIPO_COBRANZA = [
        '03' => 'BV',
        '07' => 'NC',
    ];

    public function __construct(
        private readonly GeneradorCorrelativo $correlativo,
        private readonly ApiGoEmisionService $emisionSunat,
        private readonly PrecioCalculador $precios,
        private readonly CodigoQrService $codigoQr,
    ) {}

    /** Página de alta de comprobante: monto único o detalle de productos. */
    public function createFactura(Request $request): View
    {
        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.ventas.factura', [
            'tipos' => self::TIPOS,
            'clientes' => Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento', 'direccion', 'distrito', 'provincia', 'departamento']),
            'almacenes' => $almacenes,
            // El primero que se registró (menor id), no el primero del
            // combo (que va alfabético) — así en producción siempre cae
            // en el almacén principal sin que el usuario tenga que elegirlo.
            'almacenPredeterminado' => $almacenes->min('id'),
            'productos' => Producto::activos()->with(['categoria:id,nombre', 'marca:id,nombre'])->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'presentacion', 'categoria_id', 'marca_id', 'precio_venta', 'stock']),
            // Cotización y Nota de Venta no admiten número libre: se muestra
            // de una vez el correlativo que le tocará al guardar. Boleta y
            // Factura siguen siendo editables (puede venir de un talonario
            // físico), pero se sugiere el correlativo real de SUNAT cuando
            // API-GO responde — si no, el campo queda vacío y se escribe a
            // mano como siempre; `crearComprobante()` corrige el número
            // final de todas formas si no coincide con el real.
            'correlativosInternos' => [
                'COT' => $this->correlativo->documentoInterno('COT', self::TIPOS['COT']['serie']),
                'NV' => $this->correlativo->documentoInterno('NV', self::TIPOS['NV']['serie']),
                '01' => $this->sugerenciaCorrelativoSunat('01'),
                '03' => $this->sugerenciaCorrelativoSunat('03'),
            ],
            'origen' => $this->origenParaFactura($request),
            'metodosPago' => MetodoPago::activosOrdenados(),
            'mediosPagoBancarizacion' => config('empresas.activa.sunat_habilitado', true)
                ? $this->emisionSunat->mediosPagoBancarizacion()
                : [],
        ]);
    }

    /**
     * Sugerencia de solo lectura para el N° Comprobante de Boleta/Factura
     * (null si está deshabilitado o API-GO no respondió) — nunca bloquea la
     * carga del formulario por esto.
     *
     * El contador real de SUNAT solo avanza cuando un comprobante se
     * registra con éxito — si uno se quedó sin registrarse (ej. rechazado
     * por bancarización faltante, como pasó el 6 de octubre con la venta
     * #211), ese número sigue "atascado" como la sugerencia de TODAS las
     * Facturas nuevas, cada una choca contra el duplicado local y queda
     * bloqueada. Se salta hacia adelante hasta un número que de verdad esté
     * libre en este sistema, para que una sola Factura pendiente no vuelva
     * a frenarle la numeración a todo el negocio.
     */
    private function sugerenciaCorrelativoSunat(string $tipcomp): ?string
    {
        if (! config('empresas.activa.sunat_habilitado', true)) {
            return null;
        }

        $siguiente = $this->emisionSunat->siguienteCorrelativo($tipcomp, self::TIPOS[$tipcomp]['serie']);

        if ($siguiente === null) {
            return null;
        }

        $serie = self::TIPOS[$tipcomp]['serie'];

        while (Venta::where('tipcomp', $tipcomp)->where('n_seri', $serie)->where('n_comp', $siguiente)->exists()) {
            $siguiente = str_pad((string) ((int) $siguiente + 1), strlen($siguiente), '0', STR_PAD_LEFT);
        }

        return $siguiente;
    }

    /**
     * Precarga desde una Cotización existente (`?desde=`), para "Generar
     * venta" en Nota de Venta/Boleta/Factura sin volver a escribir cliente
     * ni productos. Si el id no corresponde a una Cotización, se ignora en
     * silencio y el formulario abre vacío como siempre.
     */
    private function origenParaFactura(Request $request): ?array
    {
        $origenId = $request->query('desde');

        if (! $origenId) {
            return null;
        }

        $venta = Venta::with('detalles')->where('tipcomp', 'COT')->find($origenId);

        if (! $venta) {
            return null;
        }

        $tieneItems = $venta->detalles->isNotEmpty();

        return [
            'id' => $venta->id,
            'comprobante' => "{$venta->n_seri}-{$venta->n_comp}",
            'razonsocial' => $venta->razonsocial,
            'n_ruc' => $venta->n_ruc,
            'direccion' => $venta->cliente_direccion,
            'distrito' => $venta->cliente_distrito,
            'provincia' => $venta->cliente_provincia,
            'departamento' => $venta->cliente_departamento,
            'cliente_id' => $venta->cliente_id,
            'items' => $venta->detalles->map(fn (VentaDetalle $d) => [
                'nombre' => $d->prod_nombre,
                'codigo' => $d->prod_codigo,
                'precio' => (float) $d->precio_unitario,
                'cantidad' => (float) $d->cantidad,
            ])->values(),
            // Solo tiene sentido cuando no hay items: el monto único que se
            // usó en la cotización, ya desglosado a bruto para el formulario.
            'monto' => $tieneItems ? 0.0 : round((float) $venta->baseimp + (float) $venta->igv + (float) $venta->exonerado + (float) $venta->inafecto, 2),
            'tipo_operacion' => $venta->baseimp > 0 ? 'gravada' : ($venta->exonerado > 0 ? 'exonerada' : 'inafecta'),
        ];
    }

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $mes      = trim((string) $request->query('mes', ''));   // formato YYYY-MM
        $desde    = trim((string) $request->query('desde', ''));
        $hasta    = trim((string) $request->query('hasta', ''));
        // Vistas rápidas del sidebar: "No enviados" (estado_factura=no_enviado)
        // y "Anulaciones" (estado=cancelada) — ninguna reemplaza los filtros
        // normales, se combinan con ellos.
        $estadoFactura = trim((string) $request->query('estado_factura', ''));
        $estadoFiltro  = trim((string) $request->query('estado', ''));
        // Lista propia por tipo (Cotizaciones/Notas de Venta/Boletas/Facturas
        // del submenú): filtra sobre el mismo listado, no es una vista aparte.
        $tipcomp = trim((string) $request->query('tipcomp', ''));
        // Solo tiene sentido dentro de la lista de Cotizaciones: si ya se
        // generó (o no) la venta real (Nota de Venta/Boleta/Factura) desde ahí.
        $convertida = trim((string) $request->query('convertida', ''));

        $filtrada = Venta::query()
            ->when(
                $tipcomp !== '',
                fn ($query) => $query->where('tipcomp', $tipcomp),
                // Sin un tipo pedido explícitamente, la Cotización no cuenta:
                // es un presupuesto, no una venta comprometida — mezclarla
                // aquí duplicaba el monto (cotización + la venta generada
                // desde ella) y complicaba el cierre de caja. Excepción:
                // "Anulaciones" sí quiere ver de todo, cotizaciones
                // eliminadas incluidas — por eso esta exclusión se salta ahí.
                fn ($query) => $estadoFiltro === 'cancelada' ? $query : $query->sinCotizaciones()
            )
            ->when($tipcomp === 'COT' && $convertida === 'si', fn ($q) => $q->has('ventaGenerada'))
            ->when($tipcomp === 'COT' && $convertida === 'no', fn ($q) => $q->doesntHave('ventaGenerada'))
            ->when($estadoFiltro === 'cancelada', function ($query) {
                // "Anulaciones": une lo anulado (Nota de Venta/Boleta/Factura
                // ya comprometidas) y lo eliminado (Cotización, o cualquiera
                // de las otras si nunca llegó a comprometerse) en una sola
                // lista — antes "Eliminar" borraba la fila para siempre, sin
                // dejar ningún rastro acá.
                $query->whereIn('estado', ['cancelada', 'eliminada']);
            }, function ($query) {
                // Las canceladas y eliminadas quedan fuera del registro y de los
                // totales por defecto, el mismo criterio de `administrador/ventas.php`.
                $query->where(function ($q) {
                    $q->whereNull('estado')
                        ->orWhereNotIn('estado', ['cancelada', 'eliminada']);
                });
            })
            ->when($estadoFactura === 'no_enviado', function ($query) {
                // 'pendiente' es el valor por defecto de la columna: nunca se
                // intentó registrar en API-GO. No confundir con 'registrado'
                // (ya está en API-GO, solo falta enviarlo a SUNAT) — ambos
                // técnicamente "no enviados", pero el segundo ya tiene un
                // api_go_document_id y anularlo dejaría un registro huérfano
                // allá, así que esta vista y `anular()` solo miran 'pendiente'.
                $query->whereIn('tipcomp', ['01', '03'])->where('estado_factura', 'pendiente');
            })
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('n_comp', 'like', "%{$busqueda}%")
                        ->orWhere('n_seri', 'like', "%{$busqueda}%")
                        ->orWhere('razonsocial', 'like', "%{$busqueda}%")
                        ->orWhere('n_ruc', 'like', "%{$busqueda}%")
                        ->orWhere('numero_venta', 'like', "%{$busqueda}%");
                });
            })
            ->when($mes !== '', fn ($q) => $q->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$mes]))
            ->when($desde !== '', fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta !== '', fn ($q) => $q->whereDate('fecha', '<=', $hasta));

        $ventas = (clone $filtrada)
            ->when($tipcomp === 'COT', fn ($q) => $q->with('ventaGenerada:id,tipcomp,n_seri,n_comp,origen_cotizacion_id'))
            ->orderByDesc('fecha')
            ->orderByDesc('n_comp')
            ->get();

        // El listado se agrupa por mes, como en el original.
        $grupos = $ventas->groupBy(fn (Venta $v) => $v->fecha?->format('Y-m') ?? '');

        // Una Nota de Crédito reduce lo vendido, no lo aumenta — se resta en
        // vez de sumarse como el resto de comprobantes (la de Débito sí suma
        // normal, ya trae su propio monto positivo).
        $signo = fn (Venta $v) => $v->tipcomp === '07' ? -1 : 1;

        return view('admin.ventas.index', [
            'grupos'     => $grupos,
            'busqueda'   => $busqueda,
            'mesSel'     => $mes,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'estadoFactura' => $estadoFactura,
            'estadoFiltro'  => $estadoFiltro,
            'tipcompFiltro' => $tipcomp,
            'convertidaFiltro' => $convertida,
            'tipos'      => self::TIPOS,
            'nVentas'    => $ventas->count(),
            'totalBase'  => (float) $ventas->sum(fn (Venta $v) => $signo($v) * (float) $v->baseimp),
            'totalSinIgv' => (float) $ventas->sum(fn (Venta $v) => $signo($v) * ((float) $v->exonerado + (float) $v->inafecto)),
            'totalIgv'   => (float) $ventas->sum(fn (Venta $v) => $signo($v) * (float) $v->igv),
            'totalGeneral' => (float) $ventas->sum(fn (Venta $v) => $signo($v) * (float) $v->total),
            'clientes'     => Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->conImportes($this->validar($request));

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->exists();

        if ($duplicado) {
            return back()->with('error', "Ya existe el comprobante {$datos['n_seri']}-{$datos['n_comp']}.");
        }

        $cliente = $this->fichaDelCliente($datos);

        Venta::create($datos + [
            'estado'            => 'activa',
            'usuario_id'        => $request->user()->id,
            'cliente_id'        => $cliente?->id,
            'cliente_nombre'    => $datos['razonsocial'] ?? null,
            'cliente_ruc'       => $datos['n_ruc'] ?? null,
            'cliente_direccion' => $cliente?->direccion,
            'cliente_telefono'  => $cliente?->telefono,
            'cliente_correo'    => $cliente?->email,
            'cliente_distrito'  => $cliente?->distrito,
        ]);

        return back()->with('mensaje', "Comprobante {$datos['n_seri']}-{$datos['n_comp']} registrado.");
    }

    /**
     * Genera un comprobante para un cliente: si llegan ítems calcula el
     * subtotal/IGV/total a partir de ellos y guarda el detalle de productos;
     * si no, cae al monto único (igual que `store()`). En ambos casos crea la
     * cobranza pendiente, que refleja automáticamente la venta agregada.
     */
    public function storeFactura(Request $request): RedirectResponse
    {
        // Idempotencia: un reenvío del mismo formulario (doble clic, red
        // lenta, o volver con el botón "atrás" del navegador a mitad de la
        // redirección y que el navegador reintente el POST) no debe crear un
        // segundo comprobante — mismo patrón que ya usa el POS con
        // `pos_token`. El campo se llama distinto en el formulario, pero
        // reutiliza la misma columna.
        $formToken = trim((string) $request->input('form_token', ''));

        if ($formToken !== '') {
            $existente = Venta::where('pos_token', $formToken)->first();

            if ($existente) {
                return redirect()->route('admin.ventas.comprobante', $existente)
                    ->with('mensaje', "Comprobante {$existente->n_seri}-{$existente->n_comp} generado para {$existente->cliente_nombre}.");
            }
        }

        $datos = $this->conNumeroInterno($this->validarFactura($request));
        $items = $this->itemsValidos($datos['items'] ?? []);
        $items = $this->resolverProductoIds($items);

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->first();

        if ($duplicado) {
            // Si ese "duplicado" nunca llegó a registrarse ante SUNAT, el
            // mensaje genérico de siempre deja a la persona sin saber qué
            // hacer — se le indica el arreglo real en vez de un callejón
            // sin salida (ver sugerenciaCorrelativoSunat(), que ya evita
            // que esto vuelva a pasar para los próximos comprobantes).
            $ayuda = ! $duplicado->api_go_document_id
                ? ' Ese comprobante nunca se registró ante SUNAT — abre su comprobante y usa "Reintentar registro SUNAT", o cambia el número aquí.'
                : '';

            return back()->withInput()->with('error', "Ya existe el comprobante {$datos['n_seri']}-{$datos['n_comp']}.{$ayuda}");
        }

        $importes = $this->calcularImportes($datos, $items);

        if ($importes === null) {
            return back()->withInput()->with('error', 'Ingresa un monto o agrega al menos un producto.');
        }

        $origenCotizacion = ! empty($datos['origen_id'])
            ? Venta::where('tipcomp', 'COT')->find($datos['origen_id'])
            : null;

        // Sin este freno, volver con el botón "atrás" del navegador a este
        // mismo formulario precargado (ya generó la venta, pero la persona
        // no se dio cuenta) y presionar "Guardar" de nuevo genera una
        // SEGUNDA venta real desde la misma Cotización — `ventaGenerada()`
        // es un hasOne, así que la vieja queda invisible en "Convertida en"
        // aunque siga existiendo de verdad en el listado (el "duplicado" que
        // reportó el negocio). Solo cuenta una conversión vigente (una
        // anulada/eliminada no bloquea volver a generar).
        if ($origenCotizacion) {
            $conversionExistente = Venta::where('origen_cotizacion_id', $origenCotizacion->id)
                ->where(fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'eliminada']))
                ->first();

            if ($conversionExistente) {
                return back()->with(
                    'error',
                    "Esta Cotización ya generó el comprobante {$conversionExistente->n_seri}-{$conversionExistente->n_comp} — anúlalo o elimínalo antes de generar otro."
                );
            }
        }

        $almacenId = ! empty($datos['almacen_id']) ? (int) $datos['almacen_id'] : null;

        // Una Cotización es un presupuesto: nunca mueve stock, sin importar
        // si el usuario igual eligió un almacén. Para el resto, si al menos
        // una línea sí enlazó un producto real del catálogo, hace falta
        // saber de qué almacén sale para poder descontarlo.
        $aplicaStock = $datos['tipcomp'] !== 'COT'
            && collect($items)->contains(fn (array $item) => $item['producto_id'] !== null);

        if ($aplicaStock && ! $almacenId) {
            throw ValidationException::withMessages([
                'almacen_id' => 'Selecciona un almacén: hay productos del catálogo en el detalle y su stock debe descontarse.',
            ]);
        }

        // Una Cotización es un presupuesto: todavía no hay un pago real que
        // registrar. Nota de Venta, Boleta y Factura sí son una venta
        // comprometida — hace falta saber cómo se está cobrando (uno o
        // varios medios, ej. parte Yape + parte Efectivo).
        $pagos = $this->pagosValidos($datos['pagos'] ?? []);

        if ($datos['tipcomp'] !== 'COT' && $pagos === []) {
            throw ValidationException::withMessages([
                'pagos' => 'Registra al menos un medio de pago.',
            ]);
        }

        $metodoPago = $this->resumenMetodoPago($pagos);

        // El negocio pidió explícitamente que la venta NUNCA se bloquee por
        // falta de stock (a diferencia del POS, que sí bloquea) — se
        // descuenta igual, queda en negativo si hace falta, y se avisa
        // después de guardar (no se corta la venta a mitad de camino).
        $avisosStock = [];

        $venta = DB::transaction(function () use ($request, $datos, $items, $importes, $origenCotizacion, $almacenId, $aplicaStock, $pagos, $metodoPago, $formToken, &$avisosStock) {
            $stockFilas = collect();

            if ($aplicaStock) {
                $productoIds = collect($items)->pluck('producto_id')->filter()->unique()->values()->all();

                $stockFilas = StockAlmacen::where('almacen_id', $almacenId)
                    ->whereIn('producto_id', $productoIds)
                    ->orderBy('producto_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('producto_id');
            }

            $cliente = $this->fichaDelCliente($datos);

            // La cobranza dispara `Cobranza::reflejarEnVentas()`, que crea la
            // venta agregada automáticamente (ver Cobranza::booted()).
            $cobranza = new Cobranza([
                'tipo' => self::TIPO_COBRANZA[$datos['tipcomp']] ?? 'FT',
                'numero' => trim($datos['n_seri'].'-'.$datos['n_comp'], '-'),
                'fecha_emision' => $datos['fecha'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'],
                'cliente_nombre' => $datos['razonsocial'],
                'cliente_id' => $cliente?->id,
                'monto_total' => $importes['total'],
                'monto_pagado' => 0,
                'usuario_id' => $request->user()->id,
            ]);
            $cobranza->recalcularEstado();
            $cobranza->save();

            $venta = Venta::where('cobranza_id', $cobranza->id)->firstOrFail();

            $venta->update([
                'numero_venta' => $this->correlativo->venta(),
                // `Cobranza::reflejarEnVentas()` solo reconoce tipos SUNAT (BV/NC)
                // y cae a Factura para cualquier otro — se corrige aquí con el
                // tipo real que eligió el usuario (necesario para "NV").
                'tipcomp' => $datos['tipcomp'],
                'tipo_comprobante' => self::TIPOS[$datos['tipcomp']]['nombre'] ?? null,
                'n_ruc' => $datos['n_ruc'] ?? '',
                'cliente_ruc' => $datos['n_ruc'] ?? null,
                // Dirección: la que se escribió a mano en el formulario tiene
                // prioridad — si se dejó en blanco, cae a la de la ficha del
                // cliente elegido (si hay una).
                'cliente_direccion' => ! empty($datos['direccion']) ? $datos['direccion'] : $cliente?->direccion,
                'cliente_telefono' => $cliente?->telefono,
                'cliente_correo' => $cliente?->email,
                'cliente_distrito' => ! empty($datos['distrito']) ? $datos['distrito'] : $cliente?->distrito,
                'cliente_provincia' => ! empty($datos['provincia']) ? $datos['provincia'] : $cliente?->provincia,
                'cliente_departamento' => ! empty($datos['departamento']) ? $datos['departamento'] : $cliente?->departamento,
                'bancarizacion_medio_pago' => $datos['bancarizacion_medio_pago'] ?? null,
                'bancarizacion_numero_operacion' => $datos['bancarizacion_numero_operacion'] ?? null,
                'bancarizacion_fecha_pago' => $datos['bancarizacion_fecha_pago'] ?? null,
                'bancarizacion_banco' => $datos['bancarizacion_banco'] ?? null,
                'bancarizacion_observaciones' => $datos['bancarizacion_observaciones'] ?? null,
                'condicion_pago' => $datos['condicion_pago'] ?? null,
                'metodo_pago' => $metodoPago,
                'almacen_id' => $almacenId,
                'baseimp' => $importes['baseimp'],
                'subtotal' => round($importes['baseimp'] + $importes['exonerado'] + $importes['inafecto'], 2),
                'igv' => $importes['igv'],
                'exonerado' => $importes['exonerado'],
                'inafecto' => $importes['inafecto'],
                'total' => $importes['total'],
                'moneda' => 'PEN',
                'tipo_cambio' => 1,
                'tipcambio' => 1,
                'observaciones' => $origenCotizacion
                    ? "Generado desde Cotización {$origenCotizacion->n_seri}-{$origenCotizacion->n_comp}"
                    : null,
                'origen_cotizacion_id' => $origenCotizacion?->id,
                'pos_token' => $formToken !== '' ? $formToken : null,
            ]);

            foreach ($items as $item) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    // Ya viene resuelto por resolverProductoIds(), antes de
                    // abrir esta transacción — se enlaza cuando el código
                    // existe, para que el comprobante pueda mostrar la
                    // unidad del producto.
                    'producto_id' => $item['producto_id'],
                    'prod_codigo' => $item['producto_codigo'] ?: null,
                    'prod_nombre' => $item['producto_nombre'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2),
                ]);
            }

            foreach ($pagos as $pago) {
                $venta->pagos()->create($pago);
            }

            // Stock: se descuenta igual aunque no alcance — el negocio pidió
            // que la venta nunca se corte por esto (a diferencia del POS).
            // Como itemsValidos() no fusiona líneas repetidas del mismo
            // producto, el descuento va en el mismo paso por línea: así la
            // segunda línea de un mismo producto ya ve el descuento de la
            // primera y el negativo refleja la demanda combinada real, no
            // solo la de una línea aislada.
            if ($aplicaStock) {
                foreach ($items as $item) {
                    if ($item['producto_id'] === null) {
                        continue;
                    }

                    // Si el producto nunca tuvo stock registrado en este
                    // almacén, $stockFilas no trae su fila (el WHERE IN de
                    // arriba solo lee filas existentes) — se crea en cero
                    // en vez de reventar con un ->update() sobre null.
                    $fila = $stockFilas->get($item['producto_id']) ?? StockAlmacen::lockForUpdate()->firstOrCreate(
                        ['producto_id' => $item['producto_id'], 'almacen_id' => $almacenId],
                        ['stock' => 0]
                    );

                    $disponible = (float) $fila->stock;
                    $nuevo = $disponible - $item['cantidad'];
                    $fila->update(['stock' => $nuevo]);

                    if ($nuevo < 0) {
                        $avisosStock[] = "{$item['producto_nombre']} quedó en {$nuevo}";
                    }

                    MovimientoAlmacen::create([
                        'producto_id' => $item['producto_id'],
                        'almacen_id' => $almacenId,
                        'tipo' => 'salida',
                        'cantidad' => $item['cantidad'],
                        'stock_anterior' => $disponible,
                        'stock_nuevo' => $nuevo,
                        'motivo' => "Venta {$venta->numero_venta}".($nuevo < 0 ? ' (sin stock suficiente)' : ''),
                        'referencia' => $venta->numero_venta,
                        'usuario_id' => $request->user()->id,
                    ]);

                    Producto::find($item['producto_id'])?->recalcularStock();
                }
            }

            // Una Cotización es un presupuesto, no una deuda real: no debe
            // pesar en Cobranzas ni en "Por cobrar" del Dashboard. Se
            // aprovechó el mecanismo de Cobranza::reflejarEnVentas() de arriba
            // solo para construir la fila de `ventas` (es el único camino que
            // tiene este endpoint); acá se descarta esa cobranza — pero
            // primero se desvincula la venta, porque `Cobranza::booted()`
            // borra en cascada cualquier venta que siga apuntando a ella.
            if ($datos['tipcomp'] === 'COT') {
                $venta->update(['cobranza_id' => null]);
                $cobranza->delete();
            }

            return $venta;
        });

        // Registro en el sistema de facturación electrónica (API-GO). Va
        // fuera de la transacción y protegido por try/catch: si el servicio
        // está caído la venta ya quedó guardada y no debe bloquearse por esto.
        // Empresas sin SUNAT habilitado (ver config/empresas.php) no llaman
        // a la API-GO en absoluto: el comprobante queda solo como registro
        // interno, nunca se intenta emitir electrónicamente.
        if (config('empresas.activa.sunat_habilitado', true)) {
            try {
                $this->emisionSunat->crearComprobante($venta);
            } catch (\Throwable $e) {
                Log::warning('Fallo al registrar el comprobante en API-GO', [
                    'venta_id' => $venta->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }

        // Se abre el comprobante recién generado, listo para imprimir o enviar.
        $mensaje = "Comprobante {$venta->n_seri}-{$venta->n_comp} generado para {$venta->cliente_nombre}.";

        if ($avisosStock !== []) {
            $mensaje .= ' ⚠ Sin stock suficiente — '.implode('; ', $avisosStock).'.';
        }

        return redirect()->route('admin.ventas.comprobante', $venta)->with('mensaje', $mensaje);
    }

    /**
     * Página de edición de Cotización/Nota de Venta — reusa el mismo
     * formulario de `createFactura()` (`admin.ventas.factura`), precargado
     * con los datos de `$venta`. Boleta/Factura ya comprometidas con SUNAT
     * no pasan por acá: se corrigen con una Nota de Crédito, no editando el
     * detalle (ver el docblock de `anular()`).
     */
    public function editFactura(Venta $venta): View
    {
        abort_unless(in_array($venta->tipcomp, ['COT', 'NV'], true), 404);

        $venta->load('detalles', 'pagos');

        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.ventas.factura', [
            'tipos' => self::TIPOS,
            'clientes' => Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento', 'direccion', 'distrito', 'provincia', 'departamento']),
            'almacenes' => $almacenes,
            'almacenPredeterminado' => $almacenes->min('id'),
            'productos' => Producto::activos()->with(['categoria:id,nombre', 'marca:id,nombre'])->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'presentacion', 'categoria_id', 'marca_id', 'precio_venta', 'stock']),
            'correlativosInternos' => [],
            'origen' => null,
            'venta' => $venta,
            'metodosPago' => MetodoPago::activosOrdenados(),
        ]);
    }

    /**
     * Guarda los cambios de `editFactura()`, incluido el detalle de
     * productos (añadir/quitar líneas) — antes de esto, la edición de una
     * venta solo tocaba la cabecera y nunca el detalle ni el stock.
     */
    public function updateFactura(Request $request, Venta $venta): RedirectResponse
    {
        abort_unless(in_array($venta->tipcomp, ['COT', 'NV'], true), 404);

        $datos = $this->validarFactura($request);
        $items = $this->resolverProductoIds($this->itemsValidos($datos['items'] ?? []));

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->where('id', '!=', $venta->id)
            ->exists();

        if ($duplicado) {
            return back()->withInput()->with('error', "Ya existe otro comprobante {$datos['n_seri']}-{$datos['n_comp']}.");
        }

        $importes = $this->calcularImportes($datos, $items);

        if ($importes === null) {
            return back()->withInput()->with('error', 'Ingresa un monto o agrega al menos un producto.');
        }

        $almacenId = ! empty($datos['almacen_id']) ? (int) $datos['almacen_id'] : null;

        $aplicaStock = $datos['tipcomp'] !== 'COT'
            && collect($items)->contains(fn (array $item) => $item['producto_id'] !== null);

        if ($aplicaStock && ! $almacenId) {
            throw ValidationException::withMessages([
                'almacen_id' => 'Selecciona un almacén: hay productos del catálogo en el detalle y su stock debe descontarse.',
            ]);
        }

        $pagos = $this->pagosValidos($datos['pagos'] ?? []);

        if ($datos['tipcomp'] !== 'COT' && $pagos === []) {
            throw ValidationException::withMessages([
                'pagos' => 'Registra al menos un medio de pago.',
            ]);
        }

        $metodoPago = $this->resumenMetodoPago($pagos);

        $cliente = $this->fichaDelCliente($datos);
        $avisosStock = [];

        DB::transaction(function () use ($request, $venta, $datos, $items, $importes, $almacenId, $cliente, $pagos, $metodoPago, &$avisosStock) {
            $venta->loadMissing('detalles');

            $oldAlmacenId = $venta->almacen_id;

            $oldQtyPorProducto = $venta->detalles
                ->filter(fn (VentaDetalle $d) => $d->producto_id !== null)
                ->groupBy('producto_id')
                ->map(fn ($g) => (float) $g->sum('cantidad'));

            $newQtyPorProducto = collect($items)
                ->filter(fn (array $i) => $i['producto_id'] !== null)
                ->groupBy('producto_id')
                ->map(fn ($g) => (float) $g->sum('cantidad'));

            // Una Cotización es un presupuesto: nunca movió stock al crearse
            // (sin importar si sus líneas enlazan productos reales, ver
            // `$aplicaStock` en storeFactura()) — tampoco al editarla, por
            // más que `$venta->detalles` sí tenga `producto_id`.
            if ($datos['tipcomp'] !== 'COT') {
                // Solo se mueve la diferencia entre lo que se vendía antes
                // de editar y lo que se vende ahora — no se descuenta todo
                // de nuevo cada vez que se edita, para no ensuciar
                // Movimientos con pares de entrada/salida que se cancelan
                // entre sí. Si además cambió el almacén, no hay
                // "diferencia" que valga: se revierte todo lo viejo en el
                // almacén viejo y se aplica todo lo nuevo en el nuevo.
                if ($oldAlmacenId === $almacenId) {
                    $productoIds = $oldQtyPorProducto->keys()->merge($newQtyPorProducto->keys())->unique();

                    foreach ($productoIds as $productoId) {
                        $delta = ($newQtyPorProducto[$productoId] ?? 0) - ($oldQtyPorProducto[$productoId] ?? 0);

                        // Comparación floja a propósito: con cantidades decimales,
                        // 0.0 !== 0 (son tipos distintos) dispararía un movimiento
                        // de stock vacío en cada edición sin cambios reales.
                        if ($delta != 0 && $almacenId) {
                            $this->ajustarStockDelta((int) $productoId, $almacenId, $delta, $venta, $request, $avisosStock);
                        }
                    }
                } else {
                    if ($oldAlmacenId) {
                        foreach ($oldQtyPorProducto as $productoId => $qty) {
                            $this->ajustarStockDelta((int) $productoId, $oldAlmacenId, -$qty, $venta, $request, $avisosStock);
                        }
                    }

                    if ($almacenId) {
                        foreach ($newQtyPorProducto as $productoId => $qty) {
                            $this->ajustarStockDelta((int) $productoId, $almacenId, $qty, $venta, $request, $avisosStock);
                        }
                    }
                }
            }

            $venta->detalles()->delete();

            foreach ($items as $item) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_id'],
                    'prod_codigo' => $item['producto_codigo'] ?: null,
                    'prod_nombre' => $item['producto_nombre'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2),
                ]);
            }

            // Se reemplaza el desglose completo en vez de tratar de calzar
            // filas viejas con nuevas: más simple, y de todos modos siempre
            // se manda la lista completa vigente desde el formulario.
            $venta->pagos()->delete();

            foreach ($pagos as $pago) {
                $venta->pagos()->create($pago);
            }

            // La Cobranza vinculada (si la Nota de Venta tiene una) no se
            // toca acá a propósito: `Cobranza::reflejarEnVentas()` pisaría
            // `tipcomp` de vuelta a '01' (ver el comentario en storeFactura()
            // sobre por qué hace falta corregirlo después de guardar la
            // cobranza) — mismo límite que ya tenía `update()` antes de este
            // cambio, no es nuevo.
            $venta->update([
                'fecha' => $datos['fecha'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'],
                'n_seri' => $datos['n_seri'],
                'n_comp' => $datos['n_comp'],
                'n_ruc' => $datos['n_ruc'] ?? '',
                'razonsocial' => $datos['razonsocial'],
                'cliente_id' => $cliente?->id,
                'cliente_ruc' => $datos['n_ruc'] ?? null,
                'cliente_nombre' => $datos['razonsocial'],
                'cliente_direccion' => ! empty($datos['direccion']) ? $datos['direccion'] : $cliente?->direccion,
                'cliente_telefono' => $cliente?->telefono,
                'cliente_correo' => $cliente?->email,
                'cliente_distrito' => ! empty($datos['distrito']) ? $datos['distrito'] : $cliente?->distrito,
                'cliente_provincia' => ! empty($datos['provincia']) ? $datos['provincia'] : $cliente?->provincia,
                'cliente_departamento' => ! empty($datos['departamento']) ? $datos['departamento'] : $cliente?->departamento,
                'condicion_pago' => $datos['condicion_pago'] ?? null,
                'metodo_pago' => $metodoPago,
                'almacen_id' => $almacenId,
                'baseimp' => $importes['baseimp'],
                'subtotal' => round($importes['baseimp'] + $importes['exonerado'] + $importes['inafecto'], 2),
                'igv' => $importes['igv'],
                'exonerado' => $importes['exonerado'],
                'inafecto' => $importes['inafecto'],
                'total' => $importes['total'],
                'moneda' => 'PEN',
                'tipo_cambio' => 1,
                'tipcambio' => 1,
            ]);
        });

        $mensaje = "Comprobante {$venta->n_seri}-{$venta->n_comp} actualizado.";

        if ($avisosStock !== []) {
            $mensaje .= ' ⚠ Sin stock suficiente — '.implode('; ', $avisosStock).'.';
        }

        return redirect()->route('admin.ventas.comprobante', $venta)->with('mensaje', $mensaje);
    }

    /** Página de alta de Nota de Crédito/Débito, opcionalmente preseleccionando el comprobante a corregir. */
    public function createNota(?Venta $origen = null): View
    {
        return view('admin.ventas.nota', [
            'origen' => $origen,
            'comprobantes' => Venta::where('estado_factura', 'aceptado')
                ->whereIn('tipcomp', ['01', '03'])
                ->orderByDesc('fecha')
                ->get(['id', 'tipcomp', 'n_seri', 'n_comp', 'cliente_nombre', 'razonsocial', 'total']),
            'motivosCredito' => Venta::MOTIVOS_CREDITO,
            'motivosDebito' => Venta::MOTIVOS_DEBITO,
            'productos' => Producto::activos()->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'presentacion', 'precio_venta']),
        ]);
    }

    /**
     * Genera una Nota de Crédito/Débito que corrige un comprobante ya
     * aceptado por SUNAT. Los datos del cliente se copian del comprobante
     * origen (no de una búsqueda nueva en Clientes), porque ese es el dato
     * que ya validó SUNAT — sin importar si la ficha del cliente cambió
     * después.
     */
    public function storeNota(Request $request): RedirectResponse
    {
        $datos = $this->validarNota($request);
        $origen = $datos['venta_origen'];
        $items = $this->itemsValidos($datos['items'] ?? []);

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->exists();

        if ($duplicado) {
            return back()->withInput()->with('error', "Ya existe el comprobante {$datos['n_seri']}-{$datos['n_comp']}.");
        }

        if ($items !== []) {
            $subtotalItems = collect($items)->sum(
                fn (array $item) => (float) $item['cantidad'] * (float) $item['precio_unitario']
            );
            $desglose = $this->precios->desglosarImporte($subtotalItems, ! empty($datos['precios_incluyen_igv']));
            $importes = [
                'baseimp' => $desglose['base'],
                'igv' => $desglose['igv'],
                'exonerado' => 0.0,
                'inafecto' => 0.0,
                'total' => round($desglose['base'] + $desglose['igv'], 2),
            ];
        } elseif ((float) ($datos['monto'] ?? 0) > 0 && ! empty($datos['tipo_operacion'])) {
            $importes = $this->conImportes([
                'monto' => $datos['monto'],
                'tipo_operacion' => $datos['tipo_operacion'],
                'precios_incluyen_igv' => $datos['precios_incluyen_igv'] ?? false,
            ]);
        } else {
            return back()->withInput()->with('error', 'Ingresa un monto o agrega al menos un producto.');
        }

        $venta = DB::transaction(function () use ($request, $datos, $origen, $items, $importes) {
            $venta = Venta::create([
                'fecha' => $datos['fecha'],
                'tipcomp' => $datos['tipcomp'],
                'tipo_comprobante' => self::TIPOS[$datos['tipcomp']]['nombre'] ?? null,
                'n_seri' => $datos['n_seri'],
                'n_comp' => $datos['n_comp'],
                'numero_venta' => $this->correlativo->venta(),
                'venta_origen_id' => $origen->id,
                'cod_motivo' => $datos['cod_motivo'],
                'estado' => 'activa',
                'usuario_id' => $request->user()->id,
                // Cliente: copiado del comprobante origen, fuente de verdad ante SUNAT.
                'cliente_id' => $origen->cliente_id,
                'cliente_nombre' => $origen->cliente_nombre,
                'razonsocial' => $origen->razonsocial,
                'n_ruc' => $origen->n_ruc,
                'cliente_ruc' => $origen->cliente_ruc,
                'cliente_direccion' => $origen->cliente_direccion,
                'cliente_telefono' => $origen->cliente_telefono,
                'cliente_correo' => $origen->cliente_correo,
                'cliente_distrito' => $origen->cliente_distrito,
                'moneda' => $origen->moneda ?: 'PEN',
                'tipo_cambio' => 1,
                'tipcambio' => 1,
                'baseimp' => $importes['baseimp'],
                'subtotal' => round($importes['baseimp'] + $importes['exonerado'] + $importes['inafecto'], 2),
                'igv' => $importes['igv'],
                'exonerado' => $importes['exonerado'],
                'inafecto' => $importes['inafecto'],
                'total' => $importes['total'],
            ]);

            foreach ($items as $item) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_codigo']
                        ? Producto::where('codigo', $item['producto_codigo'])->value('id')
                        : null,
                    'prod_codigo' => $item['producto_codigo'] ?: null,
                    'prod_nombre' => $item['producto_nombre'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2),
                ]);
            }

            return $venta;
        });

        if (config('empresas.activa.sunat_habilitado', true)) {
            try {
                $this->emisionSunat->crearComprobante($venta);
            } catch (\Throwable $e) {
                Log::warning('Fallo al registrar la nota en API-GO', [
                    'venta_id' => $venta->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.ventas.comprobante', $venta)->with(
            'mensaje',
            "Nota {$venta->n_seri}-{$venta->n_comp} generada, corresponde a {$origen->n_seri}-{$origen->n_comp}."
        );
    }

    /** Envía a SUNAT (real, vía API-GO) el comprobante ya registrado. */
    public function enviarSunat(Venta $venta): RedirectResponse
    {
        $enviado = $this->emisionSunat->enviarSunat($venta);

        return back()->with(
            $enviado ? 'mensaje' : 'error',
            $enviado
                ? 'Comprobante enviado y aceptado por SUNAT.'
                : 'SUNAT rechazó el comprobante o no se pudo enviar. Revisa el detalle abajo.'
        );
    }

    /**
     * Reintenta el registro en API-GO de un comprobante que nunca llegó a
     * registrarse ahí (sin `api_go_document_id`) — distinto de
     * `enviarSunat()`, que reenvía uno que YA está registrado localmente
     * pero no se había mandado a SUNAT. Hace falta este segundo camino
     * porque antes un fallo acá (ej. bancarización faltante en una
     * Factura) dejaba el comprobante sin ninguna forma de reintentarse
     * desde el sistema — el caso real que lo disparó: una Factura de
     * S/ 3,263.88 rechazada el 6 de octubre.
     */
    public function reintentarRegistroSunat(Request $request, Venta $venta): RedirectResponse
    {
        abort_unless(in_array($venta->tipcomp, ['01', '03'], true), 404);
        abort_if($venta->api_go_document_id, 404);

        if ($venta->tipcomp === '01') {
            $venta->update($request->validate([
                'bancarizacion_medio_pago' => ['nullable', 'string', 'max:10'],
                'bancarizacion_numero_operacion' => ['nullable', 'string', 'max:100'],
                'bancarizacion_fecha_pago' => ['nullable', 'date'],
                'bancarizacion_banco' => ['nullable', 'string', 'max:100'],
                'bancarizacion_observaciones' => ['nullable', 'string', 'max:500'],
            ]));
        }

        $registrado = $this->emisionSunat->crearComprobante($venta);

        return back()->with(
            $registrado ? 'mensaje' : 'error',
            $registrado
                ? 'Comprobante registrado correctamente en el sistema de facturación electrónica.'
                : ($venta->fresh()->nota_contadora ?: 'No se pudo registrar el comprobante. Intenta de nuevo en unos segundos.')
        );
    }

    /** Descarga el PDF oficial (firmado, generado por API-GO) del comprobante. */
    public function pdfSunat(Venta $venta): Response|RedirectResponse
    {
        $pdf = $this->emisionSunat->obtenerPdf($venta);

        if ($pdf === null) {
            return back()->with('error', 'No se pudo obtener el PDF oficial. Intenta enviarlo a SUNAT primero.');
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($venta->numero_sunat ?: $venta->numero_venta).'.pdf"',
        ]);
    }

    /**
     * Anula un comprobante que nunca llegó a comprometerse con SUNAT: Nota de
     * Venta (documento interno) o una Boleta/Factura que todavía no se
     * envió. Una Cotización no pasa por acá — al no tener ningún efecto ante
     * SUNAT ni generar cobranza, se borra directo con "Eliminar". Un
     * comprobante ya aceptado por SUNAT tampoco se anula así — se corrige
     * con una Nota de Crédito (motivo "01 — Anulación de la operación"), el
     * único mecanismo válido ante SUNAT.
     */
    public function anular(Request $request, Venta $venta): RedirectResponse
    {
        if (in_array($venta->estado, ['cancelada', 'eliminada'], true)) {
            return back()->with('error', 'Este comprobante ya fue anulado o eliminado.');
        }

        // Una Cotización es un presupuesto sin efecto ante SUNAT ni stock
        // comprometido: no hay nada que "anular" — se borra directo con
        // "Eliminar" (el botón de Anular ya ni se muestra para ella en el
        // listado, esto es la defensa del lado del servidor).
        if ($venta->tipcomp === 'COT') {
            return back()->with('error', 'Una Cotización no se anula: elimínala directamente si ya no sirve.');
        }

        if (! $this->seguroDeSunat($venta)) {
            return back()->with('error',
                'Este comprobante ya fue enviado a SUNAT: no se puede anular directamente. '.
                'Genera una Nota de Crédito con motivo "Anulación de la operación" desde el listado.'
            );
        }

        DB::transaction(function () use ($request, $venta) {
            $this->revertirStock($venta, $request->user()->id, "Anulación de venta {$venta->n_seri}-{$venta->n_comp}");

            $venta->update(['estado' => 'cancelada']);
        });

        return back()->with('mensaje', "Comprobante {$venta->n_seri}-{$venta->n_comp} anulado.");
    }

    /**
     * Copia manual de una Cotización: una fila nueva e independiente, con
     * fecha de hoy y su propio correlativo — no un comprobante "generado
     * desde" (no lleva `origen_cotizacion_id`, así que no aparece como
     * "Convertida en" de la original ni la bloquea para generar su propia
     * venta real después). Al ser Cotización, nunca mueve stock ni entra
     * en ningún total — duplicar no desalinea nada de eso.
     */
    public function duplicarCotizacion(Venta $venta): RedirectResponse
    {
        abort_unless($venta->tipcomp === 'COT', 404);

        $venta->loadMissing('detalles');

        $nueva = DB::transaction(function () use ($venta) {
            $copia = Venta::create([
                'fecha' => now()->toDateString(),
                'tipcomp' => 'COT',
                'n_seri' => self::TIPOS['COT']['serie'],
                'n_comp' => $this->correlativo->documentoInterno('COT', self::TIPOS['COT']['serie']),
                'numero_venta' => $this->correlativo->venta(),
                'estado' => 'activa',
                'razonsocial' => $venta->razonsocial,
                'n_ruc' => $venta->n_ruc,
                'cliente_id' => $venta->cliente_id,
                'cliente_nombre' => $venta->cliente_nombre,
                'cliente_ruc' => $venta->cliente_ruc,
                'cliente_direccion' => $venta->cliente_direccion,
                'cliente_telefono' => $venta->cliente_telefono,
                'cliente_correo' => $venta->cliente_correo,
                'cliente_distrito' => $venta->cliente_distrito,
                'condicion_pago' => $venta->condicion_pago,
                'baseimp' => $venta->baseimp,
                'subtotal' => $venta->subtotal,
                'igv' => $venta->igv,
                'exonerado' => $venta->exonerado,
                'inafecto' => $venta->inafecto,
                'total' => $venta->total,
                'moneda' => $venta->moneda,
                'tipo_cambio' => $venta->tipo_cambio,
                'tipcambio' => $venta->tipcambio,
            ]);

            foreach ($venta->detalles as $detalle) {
                VentaDetalle::create([
                    'venta_id' => $copia->id,
                    'producto_id' => $detalle->producto_id,
                    'prod_codigo' => $detalle->prod_codigo,
                    'prod_nombre' => $detalle->prod_nombre,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'subtotal' => $detalle->subtotal,
                ]);
            }

            return $copia;
        });

        return redirect()->route('admin.ventas.comprobante', $nueva)
            ->with('mensaje', "Cotización duplicada como {$nueva->n_seri}-{$nueva->n_comp}.");
    }

    /**
     * Si ya se envió a SUNAT (o no aplica, por ser Cotización/Nota de Venta,
     * ninguna de las dos SUNAT-electrónicas) es seguro anular o eliminar sin
     * dejar rastro huérfano allá. Boleta/Factura ya registradas solo se
     * corrigen con una Nota de Crédito.
     */
    private function seguroDeSunat(Venta $venta): bool
    {
        if (in_array($venta->tipcomp, ['COT', 'NV'], true)) {
            return true;
        }

        // Seguro de anular/eliminar directo mientras no exista nada creado
        // del lado de API-GO todavía — cubre tanto 'pendiente' (nunca se
        // intentó registrar) como 'error' (se intentó y falló, ej. la
        // bancarización faltante del 6 de octubre: ese estado nuevo dejó
        // sin querer sin esta opción a cualquier comprobante rechazado,
        // porque antes solo se comparaba contra el texto 'pendiente').
        // Desde que hay un `api_go_document_id` real, ya existe un
        // documento allá con su propio correlativo consumido de SUNAT —
        // anularlo local dejaría un registro huérfano, hace falta Nota de
        // Crédito en su lugar. El chequeo de estado_factura queda además
        // como defensa extra por si alguna vez 'aceptado'/'rechazado'
        // llegara sin el id (no debería pasar en la práctica).
        return in_array($venta->tipcomp, ['01', '03'], true)
            && ! $venta->api_go_document_id
            && ! in_array($venta->estado_factura, ['aceptado', 'rechazado'], true);
    }

    /** Devuelve al Inventario el stock que esta venta había descontado, si alguna vez descontó. */
    private function revertirStock(Venta $venta, int $usuarioId, string $motivo): void
    {
        // Las ventas viejas (o una Cotización, que nunca mueve stock) tienen
        // almacen_id=null — no hay nada que devolver, se saltan limpio.
        if (! $venta->almacen_id) {
            return;
        }

        $venta->loadMissing('detalles');

        foreach ($venta->detalles as $detalle) {
            if ($detalle->producto_id === null) {
                continue;
            }

            $fila = StockAlmacen::lockForUpdate()->firstOrCreate(
                ['producto_id' => $detalle->producto_id, 'almacen_id' => $venta->almacen_id],
                ['stock' => 0]
            );

            $anterior = (float) $fila->stock;
            $nuevo = $anterior + (float) $detalle->cantidad;
            $fila->update(['stock' => $nuevo]);

            MovimientoAlmacen::create([
                'producto_id' => $detalle->producto_id,
                'almacen_id' => $venta->almacen_id,
                'tipo' => 'entrada',
                'cantidad' => (float) $detalle->cantidad,
                'stock_anterior' => $anterior,
                'stock_nuevo' => $nuevo,
                'motivo' => $motivo,
                'referencia' => $venta->numero_venta,
                'usuario_id' => $usuarioId,
            ]);

            Producto::find($detalle->producto_id)?->recalcularStock();
        }
    }

    public function update(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $this->conImportes($this->validar($request));

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->where('id', '!=', $venta->id)
            ->exists();

        if ($duplicado) {
            return back()->with('error', "Ya existe otro comprobante {$datos['n_seri']}-{$datos['n_comp']}.");
        }

        $cliente = $this->fichaDelCliente($datos);

        $venta->update($datos + [
            'cliente_id'        => $cliente?->id ?? $venta->cliente_id,
            'cliente_nombre'    => $datos['razonsocial'] ?? $venta->cliente_nombre,
            'cliente_ruc'       => $datos['n_ruc'] ?? $venta->cliente_ruc,
            'cliente_direccion' => $cliente?->direccion ?? $venta->cliente_direccion,
            'cliente_telefono'  => $cliente?->telefono ?? $venta->cliente_telefono,
            'cliente_correo'    => $cliente?->email ?? $venta->cliente_correo,
            'cliente_distrito'  => $cliente?->distrito ?? $venta->cliente_distrito,
        ]);

        return back()->with('mensaje', "Comprobante {$venta->n_seri}-{$venta->n_comp} actualizado.");
    }

    /**
     * Baja lógica (`estado = 'eliminada'`), no un borrado real: antes esto
     * borraba la fila para siempre, sin dejar ningún rastro en "Anulaciones"
     * ni forma de revertir el stock que hubiera descontado — ya causó un
     * incidente real en producción (cobranza huérfana de una Cotización
     * borrada). Misma protección de SUNAT que `anular()`.
     */
    public function destroy(Request $request, Venta $venta): RedirectResponse
    {
        if (in_array($venta->estado, ['cancelada', 'eliminada'], true)) {
            return back()->with('error', 'Este comprobante ya fue anulado o eliminado.');
        }

        if (! $this->seguroDeSunat($venta)) {
            return back()->with('error',
                'Este comprobante ya fue enviado a SUNAT: no se puede eliminar directamente. '.
                'Genera una Nota de Crédito con motivo "Anulación de la operación" desde el listado.'
            );
        }

        $comprobante = "{$venta->n_seri}-{$venta->n_comp}";

        DB::transaction(function () use ($request, $venta) {
            $this->revertirStock($venta, $request->user()->id, "Eliminación de venta {$venta->n_seri}-{$venta->n_comp}");

            $venta->update(['estado' => 'eliminada']);
        });

        return back()->with('mensaje', "Comprobante {$comprobante} eliminado.");
    }

    /** Vista imprimible del comprobante, con el desglose de productos y el monto en letras. */
    public function comprobante(Venta $venta, NumeroALetras $numeroALetras): View
    {
        [$vista, $datos] = $this->datosComprobante($venta, $numeroALetras);

        // El catálogo de bancarización solo hace falta cuando de verdad se
        // va a poder usar: una Factura que nunca llegó a registrarse en
        // API-GO (ver reintentarRegistroSunat()) — evita golpear API-GO en
        // cada vista de un comprobante ya resuelto.
        if ($venta->tipcomp === '01' && ! $venta->api_go_document_id) {
            $datos['mediosPagoBancarizacion'] = $this->emisionSunat->mediosPagoBancarizacion();
        }

        return view($vista, $datos);
    }

    /**
     * PDF descargable de Cotización/Nota de Venta/Boleta/Factura, para mandarlo
     * al interesado — distinto del "PDF oficial" de SUNAT (`pdfSunat()`, que
     * solo existe una vez que el comprobante ya fue aceptado): este se genera
     * al vuelo desde el mismo diseño que ya se ve/imprime en pantalla, así que
     * nunca depende de ningún estado de SUNAT ni de haber emitido todavía.
     * Reusa exactamente esas vistas (con `paraDescarga: true` para ocultar la
     * barra de acciones) en vez de duplicar el diseño en una plantilla aparte:
     * su CSS ya es compatible con dompdf (tablas, sin flexbox/variables CSS
     * en el documento en sí, solo en la barra que acá se oculta).
     */
    public function descargarPdf(Venta $venta, NumeroALetras $numeroALetras): \Illuminate\Http\Response
    {
        [$vista, $datos] = $this->datosComprobante($venta, $numeroALetras);
        $datos['paraDescarga'] = true;

        $numero = $venta->n_seri && $venta->n_comp
            ? "{$venta->n_seri}-{$venta->n_comp}"
            : $venta->numero_venta;

        $pdf = Pdf::loadView($vista, $datos)->setPaper('a4', 'portrait')->output();

        // El PDF se arma bien (confirmado generándolo directo en el servidor:
        // bytes válidos, tamaño normal) — lo que se corrompe es la entrega por
        // HTTP. La sospecha real: `zlib.output_compression` de PHP (activado
        // por defecto en varios hostings compartidos) recomprime la salida
        // DESPUÉS de que el framework ya calculó el `Content-Length` sobre
        // los bytes sin comprimir — el navegador recibe un tamaño anunciado
        // que no coincide con lo que realmente llegó, y lo trata como dañado.
        // Se apaga acá puntualmente y se arma la respuesta a mano con el
        // Content-Length real, en vez de confiar en que el entorno de
        // producción lo calcule bien.
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }

        $nombreArchivo = str_replace('"', '', "{$numero}.pdf");

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Content-Length' => (string) strlen($pdf),
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /** Datos compartidos por `comprobante()` y `descargarPdf()` — misma vista, mismo desglose. */
    private function datosComprobante(Venta $venta, NumeroALetras $numeroALetras): array
    {
        $venta->load(['detalles.producto:id,presentacion', 'guias', 'ventaOrigen', 'usuario']);

        // La Cotización no es un comprobante SUNAT: usa un formato propio,
        // más simple, pensado para enviarse al cliente antes de la venta.
        if ($venta->tipcomp === 'COT') {
            return ['admin.ventas.cotizacion', [
                'venta' => $venta,
                'tipos' => self::TIPOS,
                'cuentasBancarias' => CuentaBancaria::orderBy('id')->get(),
            ]];
        }

        $moneda = $venta->moneda === 'USD' ? 'DÓLARES AMERICANOS' : 'SOLES';

        return ['admin.ventas.comprobante', [
            'venta' => $venta,
            'tipos' => self::TIPOS,
            'montoLetras' => $numeroALetras->convertir((float) $venta->total, $moneda),
            'diasCredito' => $venta->fecha && $venta->fecha_vencimiento
                ? $venta->fecha->diffInDays($venta->fecha_vencimiento)
                : null,
            'rutaQrDisco' => $this->codigoQr->rutaEnDisco($venta),
        ]];
    }

    /**
     * Imagen del código QR para el `<img>` en pantalla (dompdf, que arma el
     * PDF descargable, no puede pedirla por HTTP — usa `rutaQrDisco` directo
     * en su lugar). Reusa el mismo cacheo en disco de `CodigoQrService`, así
     * que no vuelve a golpear el servicio externo si ya se generó antes.
     */
    public function qr(Venta $venta): Response
    {
        $ruta = $this->codigoQr->rutaEnDisco($venta);

        abort_if($ruta === null, 404);

        return response(file_get_contents($ruta), 200, ['Content-Type' => 'image/png']);
    }

    public function show(Venta $venta): View
    {
        $venta->load('detalles');

        return view('admin.ventas.show', ['venta' => $venta, 'tipos' => self::TIPOS]);
    }

    /**
     * Reparte los importes según el tipo de operación: solo uno de los tres
     * conceptos lleva monto, y el IGV únicamente aplica a la operación gravada.
     */
    private function conImportes(array $datos): array
    {
        $monto = (float) ($datos['monto'] ?? 0);
        $incluyeIgv = ! empty($datos['precios_incluyen_igv']);

        $datos['baseimp']   = 0.0;
        $datos['igv']       = 0.0;
        $datos['exonerado'] = 0.0;
        $datos['inafecto']  = 0.0;

        if ($datos['tipo_operacion'] === 'gravada') {
            $desglose = $this->precios->desglosarImporte($monto, $incluyeIgv);
            $datos['baseimp'] = $desglose['base'];
            $datos['igv']     = $desglose['igv'];
        } elseif ($datos['tipo_operacion'] === 'exonerada') {
            $datos['exonerado'] = round($monto, 2);
        } else {
            $datos['inafecto'] = round($monto, 2);
        }

        $datos['total'] = round($datos['baseimp'] + $datos['igv'] + $datos['exonerado'] + $datos['inafecto'], 2);

        unset($datos['monto'], $datos['tipo_operacion'], $datos['precios_incluyen_igv']);

        return $datos;
    }

    /**
     * Cotización y Nota de Venta no llevan un número que el usuario elija a
     * mano (a diferencia de Factura/Boleta, cuyo N° Comprobante puede venir
     * de un talonario físico o de lo ya emitido en API-GO): se reemplaza
     * siempre por el siguiente correlativo, sin importar lo que haya llegado
     * en el formulario.
     */
    private function conNumeroInterno(array $datos): array
    {
        if (in_array($datos['tipcomp'], ['COT', 'NV'], true)) {
            $datos['n_comp'] = $this->correlativo->documentoInterno($datos['tipcomp'], $datos['n_seri']);
        }

        return $datos;
    }

    private function aniosDisponibles(): array
    {
        $anios = Venta::selectRaw('DISTINCT YEAR(fecha) AS anio')->orderByDesc('anio')->pluck('anio')->all();

        return $anios ?: [now()->year];
    }

    /**
     * Ficha del módulo Clientes a la que pertenece el comprobante.
     *
     * Si el usuario eligió un cliente del buscador viene ya resuelto; si escribió
     * el documento a mano se busca por ahí y, si es un DNI/RUC nuevo, se
     * registra en Clientes en ese momento; sin documento, por razón social.
     * Así el comprobante nace enlazado y no hace falta vincularlo después.
     */
    private function fichaDelCliente(array $datos): ?Cliente
    {
        if (! empty($datos['cliente_id'])) {
            return Cliente::find($datos['cliente_id']);
        }

        $porDoc = Cliente::registrarDesdeComprobante($datos['n_ruc'] ?? null, $datos['razonsocial'] ?? null, $datos);

        if ($porDoc) {
            return $porDoc;
        }

        $nombre = trim((string) ($datos['razonsocial'] ?? ''));

        if ($nombre === '') {
            return null;
        }

        return Cliente::whereRaw('LOWER(TRIM(nombres)) = ?', [mb_strtolower($nombre)])->first();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'fecha'          => ['required', 'date'],
            // Nota de Crédito/Débito (07/08) se genera solo desde `storeNota()`,
            // que exige un comprobante origen ya aceptado — no desde este alta genérica.
            'tipcomp'        => ['required', Rule::in(['COT', 'NV', '01', '03'])],
            'n_seri'         => ['required', 'string', 'max:4'],
            'n_comp'         => ['required', 'string', 'max:20'],
            'n_ruc'          => ['nullable', 'string', 'max:20'],
            'razonsocial'    => ['nullable', 'string', 'max:300'],
            'cliente_id'     => ['nullable', 'integer', 'exists:clientes,id'],
            'tipo_operacion' => ['required', Rule::in(['gravada', 'exonerada', 'inafecta'])],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'tipcambio'      => ['nullable', 'numeric', 'min:0'],
            'precios_incluyen_igv' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Sin `items` obligatorio: el usuario puede optar por un monto único
     * (`monto` + `tipo_operacion`) en vez de detallar productos. `storeFactura()`
     * exige que venga uno de los dos.
     */
    private function validarFactura(Request $request): array
    {
        return $request->validate([
            'fecha'                        => ['required', 'date'],
            'fecha_vencimiento'            => ['required', 'date', 'after_or_equal:fecha'],
            'tipcomp'                      => ['required', Rule::in(['COT', 'NV', '01', '03'])],
            'n_seri'                       => ['required', 'string', 'max:4'],
            'n_comp'                       => ['required', 'string', 'max:20'],
            'n_ruc'                        => ['nullable', 'string', 'max:20'],
            'razonsocial'                  => ['required', 'string', 'max:300'],
            'direccion'                    => ['nullable', 'string', 'max:255'],
            // No tienen campo visible todavía (solo Dirección) — se llenan
            // solas con lo que trae la búsqueda de RUC/cliente, listas para
            // cuando se arme el selector de ubigeo completo.
            'distrito'                     => ['nullable', 'string', 'max:100'],
            'provincia'                    => ['nullable', 'string', 'max:100'],
            'departamento'                 => ['nullable', 'string', 'max:100'],
            'cliente_id'                   => ['nullable', 'integer', 'exists:clientes,id'],
            // Bancarización (Ley N° 28194) — solo aplica a Factura mayor a
            // S/ 2,000, el formulario la pide condicionalmente. Nunca
            // bloquea el guardado: si API-GO la exige y no llegó, el
            // comprobante igual se guarda local y queda marcado como
            // rechazado (ver ApiGoEmisionService::crearComprobante()).
            'bancarizacion_medio_pago'      => ['nullable', 'string', 'max:10'],
            'bancarizacion_numero_operacion' => ['nullable', 'string', 'max:100'],
            'bancarizacion_fecha_pago'      => ['nullable', 'date'],
            'bancarizacion_banco'           => ['nullable', 'string', 'max:100'],
            'bancarizacion_observaciones'   => ['nullable', 'string', 'max:500'],
            'condicion_pago'               => ['nullable', 'string', 'max:100'],
            'almacen_id'                   => ['nullable', 'integer', 'exists:almacenes,id'],
            'monto'                        => ['nullable', 'numeric', 'min:0'],
            'tipo_operacion'               => ['nullable', Rule::in(['gravada', 'exonerada', 'inafecta'])],
            'items'                        => ['nullable', 'array'],
            'items.*.producto_codigo'      => ['nullable', 'string', 'max:50'],
            'items.*.producto_nombre'      => ['nullable', 'string', 'max:255'],
            'items.*.cantidad'             => ['nullable', 'numeric', 'min:0'],
            'items.*.precio_unitario'      => ['nullable', 'numeric', 'min:0'],
            'precios_incluyen_igv'         => ['nullable', 'boolean'],
            'origen_id'                    => ['nullable', 'integer', 'exists:ventas,id'],
            // Pago mixto: una Nota de Venta/Boleta/Factura puede cobrarse
            // repartida entre varios medios (ej. parte Yape, parte Efectivo)
            // — mismo patrón que ya usa el POS (`VentaPago`, una fila por
            // medio). Una Cotización no manda ninguno (todavía no hay cobro).
            'pagos'                        => ['nullable', 'array'],
            'pagos.*.metodo_pago'          => ['nullable', 'string', 'max:50'],
            'pagos.*.monto'                => ['nullable', 'numeric', 'min:0'],
            'pagos.*.referencia'           => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * Filtra filas de pago vacías o sin monto — mismo criterio que
     * `PosVentaService::pagosValidos()`, para que una fila a medio llenar
     * (el usuario agregó una segunda fila y se arrepintió) no cuente.
     */
    private function pagosValidos(array $pagos): array
    {
        return collect($pagos)
            ->map(fn ($p) => [
                'metodo_pago' => trim((string) ($p['metodo_pago'] ?? '')),
                'monto' => round((float) ($p['monto'] ?? 0), 2),
                'referencia' => trim((string) ($p['referencia'] ?? '')) ?: null,
            ])
            ->filter(fn (array $p) => $p['metodo_pago'] !== '' && $p['monto'] > 0)
            ->values()
            ->all();
    }

    /** 'Mixto' en cuanto hay más de un medio, igual que ya resume el POS. */
    private function resumenMetodoPago(array $pagos): ?string
    {
        return match (count($pagos)) {
            0 => null,
            1 => $pagos[0]['metodo_pago'],
            default => 'Mixto',
        };
    }

    /**
     * Valida el alta de Nota de Crédito/Débito. Además de las reglas de
     * formato, exige que el comprobante origen exista, sea Boleta/Factura,
     * y ya esté aceptado por SUNAT — y que el motivo elegido pertenezca al
     * catálogo correcto según se trate de crédito (07) o débito (08).
     */
    private function validarNota(Request $request): array
    {
        $datos = $request->validate([
            'fecha'                    => ['required', 'date'],
            'tipcomp'                  => ['required', Rule::in(['07', '08'])],
            'venta_origen_id'          => ['required', 'integer', 'exists:ventas,id'],
            'n_seri'                   => ['required', 'string', 'max:4'],
            'n_comp'                   => ['required', 'string', 'max:20'],
            'cod_motivo'               => ['required', 'string', 'max:2'],
            'monto'                    => ['nullable', 'numeric', 'min:0'],
            'tipo_operacion'           => ['nullable', Rule::in(['gravada', 'exonerada', 'inafecta'])],
            'items'                    => ['nullable', 'array'],
            'items.*.producto_codigo'  => ['nullable', 'string', 'max:50'],
            'items.*.producto_nombre'  => ['nullable', 'string', 'max:255'],
            'items.*.cantidad'         => ['nullable', 'numeric', 'min:0'],
            'items.*.precio_unitario'  => ['nullable', 'numeric', 'min:0'],
            'precios_incluyen_igv'     => ['nullable', 'boolean'],
        ]);

        $origen = Venta::find($datos['venta_origen_id']);

        if (! $origen || ! in_array($origen->tipcomp, ['01', '03'], true) || $origen->estado_factura !== 'aceptado') {
            throw ValidationException::withMessages([
                'venta_origen_id' => 'El comprobante seleccionado no es válido: debe ser una Boleta o Factura ya aceptada por SUNAT.',
            ]);
        }

        $motivos = $datos['tipcomp'] === '07' ? Venta::MOTIVOS_CREDITO : Venta::MOTIVOS_DEBITO;

        if (! array_key_exists($datos['cod_motivo'], $motivos)) {
            throw ValidationException::withMessages([
                'cod_motivo' => 'El motivo seleccionado no es válido para este tipo de nota.',
            ]);
        }

        $datos['venta_origen'] = $origen;

        return $datos;
    }

    /**
     * Le pega a cada línea su `producto_id` resuelto por código — una sola
     * vez, antes de abrir la transacción de `storeFactura()`, porque el
     * chequeo de stock también lo necesita (además del `VentaDetalle`).
     * `storeNota()` no usa esto: sigue resolviendo inline como siempre.
     */
    private function resolverProductoIds(array $items): array
    {
        return array_map(function (array $item) {
            $item['producto_id'] = $item['producto_codigo']
                ? Producto::where('codigo', $item['producto_codigo'])->value('id')
                : null;

            return $item;
        }, $items);
    }

    /** Descarta filas vacías o sin cantidad, y normaliza tipos. */
    private function itemsValidos(array $items): array
    {
        $validos = [];

        foreach ($items as $item) {
            $nombre = trim((string) ($item['producto_nombre'] ?? ''));
            $cantidad = (float) ($item['cantidad'] ?? 0);

            if ($nombre === '' || $cantidad <= 0) {
                continue;
            }

            $validos[] = [
                'producto_codigo' => trim((string) ($item['producto_codigo'] ?? '')) ?: null,
                'producto_nombre' => $nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) ($item['precio_unitario'] ?? 0),
            ];
        }

        return $validos;
    }

    /**
     * Igual que la rama de importes de `storeFactura()`: si hay ítems, el
     * importe sale de sumarlos; si no, del monto único. `null` cuando no
     * vino ninguno de los dos — el llamador decide el error (crear no puede
     * seguir, editar tampoco).
     */
    private function calcularImportes(array $datos, array $items): ?array
    {
        if ($items !== []) {
            $subtotalItems = collect($items)->sum(
                fn (array $item) => (float) $item['cantidad'] * (float) $item['precio_unitario']
            );
            $desglose = $this->precios->desglosarImporte($subtotalItems, ! empty($datos['precios_incluyen_igv']));

            return [
                'baseimp' => $desglose['base'],
                'igv' => $desglose['igv'],
                'exonerado' => 0.0,
                'inafecto' => 0.0,
                'total' => round($desglose['base'] + $desglose['igv'], 2),
            ];
        }

        if ((float) ($datos['monto'] ?? 0) > 0 && ! empty($datos['tipo_operacion'])) {
            return $this->conImportes([
                'monto' => $datos['monto'],
                'tipo_operacion' => $datos['tipo_operacion'],
                'precios_incluyen_igv' => $datos['precios_incluyen_igv'] ?? false,
            ]);
        }

        return null;
    }

    /**
     * Ajusta el stock de un producto por la diferencia entre lo que se
     * vendía antes de editar y lo que se vende ahora (`updateFactura()`).
     * `$delta` positivo = se vende más que antes (descuenta), negativo = se
     * vende menos (devuelve) — mismo criterio "nunca bloquea" y "puede
     * quedar negativo" que `storeFactura()`.
     */
    private function ajustarStockDelta(int $productoId, int $almacenId, float $delta, Venta $venta, Request $request, array &$avisosStock): void
    {
        $fila = StockAlmacen::lockForUpdate()->firstOrCreate(
            ['producto_id' => $productoId, 'almacen_id' => $almacenId],
            ['stock' => 0]
        );

        $anterior = (float) $fila->stock;
        $nuevo = $anterior - $delta;
        $fila->update(['stock' => $nuevo]);

        if ($nuevo < 0) {
            $nombre = Producto::find($productoId)?->nombre ?? "producto #{$productoId}";
            $avisosStock[] = "{$nombre} quedó en {$nuevo}";
        }

        MovimientoAlmacen::create([
            'producto_id' => $productoId,
            'almacen_id' => $almacenId,
            'tipo' => $delta > 0 ? 'salida' : 'entrada',
            'cantidad' => abs($delta),
            'stock_anterior' => $anterior,
            'stock_nuevo' => $nuevo,
            'motivo' => "Edición de venta {$venta->numero_venta}".($nuevo < 0 ? ' (sin stock suficiente)' : ''),
            'referencia' => $venta->numero_venta,
            'usuario_id' => $request->user()->id,
        ]);

        Producto::find($productoId)?->recalcularStock();
    }
}
