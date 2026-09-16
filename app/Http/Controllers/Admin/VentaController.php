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
use App\Services\GeneradorCorrelativo;
use App\Services\NumeroALetras;
use App\Services\PrecioCalculador;
use App\Services\Sunat\ApiGoEmisionService;
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
    ) {}

    /** Página de alta de comprobante: monto único o detalle de productos. */
    public function createFactura(Request $request): View
    {
        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.ventas.factura', [
            'tipos' => self::TIPOS,
            'clientes' => Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento']),
            'almacenes' => $almacenes,
            // El primero que se registró (menor id), no el primero del
            // combo (que va alfabético) — así en producción siempre cae
            // en el almacén principal sin que el usuario tenga que elegirlo.
            'almacenPredeterminado' => $almacenes->min('id'),
            'productos' => Producto::activos()->with(['categoria:id,nombre', 'marca:id,nombre'])->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'presentacion', 'categoria_id', 'marca_id', 'precio_venta', 'stock']),
            // Cotización y Nota de Venta no admiten número libre: se muestra
            // de una vez el correlativo que le tocará al guardar.
            'correlativosInternos' => [
                'COT' => $this->correlativo->documentoInterno('COT', self::TIPOS['COT']['serie']),
                'NV' => $this->correlativo->documentoInterno('NV', self::TIPOS['NV']['serie']),
            ],
            'origen' => $this->origenParaFactura($request),
            'metodosPago' => MetodoPago::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
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
            'cliente_id' => $venta->cliente_id,
            'items' => $venta->detalles->map(fn (VentaDetalle $d) => [
                'nombre' => $d->prod_nombre,
                'codigo' => $d->prod_codigo,
                'precio' => (float) $d->precio_unitario,
                'cantidad' => (int) $d->cantidad,
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

        $filtrada = Venta::query()
            ->when(
                $tipcomp !== '',
                fn ($query) => $query->where('tipcomp', $tipcomp),
                // Sin un tipo pedido explícitamente, la Cotización no cuenta:
                // es un presupuesto, no una venta comprometida — mezclarla
                // aquí duplicaba el monto (cotización + la venta generada
                // desde ella) y complicaba el cierre de caja.
                fn ($query) => $query->sinCotizaciones()
            )
            ->when($estadoFiltro === 'cancelada', function ($query) {
                $query->where('estado', 'cancelada');
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
        $datos = $this->conNumeroInterno($this->validarFactura($request));
        $items = $this->itemsValidos($datos['items'] ?? []);
        $items = $this->resolverProductoIds($items);

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->exists();

        if ($duplicado) {
            return back()->with('error', "Ya existe el comprobante {$datos['n_seri']}-{$datos['n_comp']}.");
        }

        $importes = $this->calcularImportes($datos, $items);

        if ($importes === null) {
            return back()->withInput()->with('error', 'Ingresa un monto o agrega al menos un producto.');
        }

        $origenCotizacion = ! empty($datos['origen_id'])
            ? Venta::where('tipcomp', 'COT')->find($datos['origen_id'])
            : null;

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
        // comprometida — hace falta saber cómo se está cobrando.
        if ($datos['tipcomp'] !== 'COT' && empty($datos['metodo_pago'])) {
            throw ValidationException::withMessages([
                'metodo_pago' => 'Selecciona el medio de pago.',
            ]);
        }

        // El negocio pidió explícitamente que la venta NUNCA se bloquee por
        // falta de stock (a diferencia del POS, que sí bloquea) — se
        // descuenta igual, queda en negativo si hace falta, y se avisa
        // después de guardar (no se corta la venta a mitad de camino).
        $avisosStock = [];

        $venta = DB::transaction(function () use ($request, $datos, $items, $importes, $origenCotizacion, $almacenId, $aplicaStock, &$avisosStock) {
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
                'cliente_direccion' => $cliente?->direccion,
                'cliente_telefono' => $cliente?->telefono,
                'cliente_correo' => $cliente?->email,
                'cliente_distrito' => $cliente?->distrito,
                'condicion_pago' => $datos['condicion_pago'] ?? null,
                'metodo_pago' => $datos['metodo_pago'] ?? null,
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

                    $disponible = (int) $fila->stock;
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

        $venta->load('detalles');

        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.ventas.factura', [
            'tipos' => self::TIPOS,
            'clientes' => Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento']),
            'almacenes' => $almacenes,
            'almacenPredeterminado' => $almacenes->min('id'),
            'productos' => Producto::activos()->with(['categoria:id,nombre', 'marca:id,nombre'])->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'presentacion', 'categoria_id', 'marca_id', 'precio_venta', 'stock']),
            'correlativosInternos' => [],
            'origen' => null,
            'venta' => $venta,
            'metodosPago' => MetodoPago::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
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

        if ($datos['tipcomp'] !== 'COT' && empty($datos['metodo_pago'])) {
            throw ValidationException::withMessages([
                'metodo_pago' => 'Selecciona el medio de pago.',
            ]);
        }

        $cliente = $this->fichaDelCliente($datos);
        $avisosStock = [];

        DB::transaction(function () use ($request, $venta, $datos, $items, $importes, $almacenId, $cliente, &$avisosStock) {
            $venta->loadMissing('detalles');

            $oldAlmacenId = $venta->almacen_id;

            $oldQtyPorProducto = $venta->detalles
                ->filter(fn (VentaDetalle $d) => $d->producto_id !== null)
                ->groupBy('producto_id')
                ->map(fn ($g) => (int) $g->sum('cantidad'));

            $newQtyPorProducto = collect($items)
                ->filter(fn (array $i) => $i['producto_id'] !== null)
                ->groupBy('producto_id')
                ->map(fn ($g) => (int) $g->sum('cantidad'));

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

                        if ($delta !== 0 && $almacenId) {
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
                'cliente_direccion' => $cliente?->direccion,
                'cliente_telefono' => $cliente?->telefono,
                'cliente_correo' => $cliente?->email,
                'cliente_distrito' => $cliente?->distrito,
                'condicion_pago' => $datos['condicion_pago'] ?? null,
                'metodo_pago' => $datos['metodo_pago'] ?? null,
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
        if ($venta->estado === 'cancelada') {
            return back()->with('error', 'Este comprobante ya fue anulado.');
        }

        // Una Cotización es un presupuesto sin efecto ante SUNAT ni stock
        // comprometido: no hay nada que "anular" — se borra directo con
        // "Eliminar" (el botón de Anular ya ni se muestra para ella en el
        // listado, esto es la defensa del lado del servidor).
        if ($venta->tipcomp === 'COT') {
            return back()->with('error', 'Una Cotización no se anula: elimínala directamente si ya no sirve.');
        }

        $esDocumentoInterno = $venta->tipcomp === 'NV';
        // 'pendiente' es el valor por defecto: nunca se intentó registrar en
        // API-GO. Cualquier otro valor ('registrado', 'aceptado', 'rechazado')
        // ya generó algún rastro allá o ante SUNAT y no se anula por aquí.
        $noEnviadoAunSunat = in_array($venta->tipcomp, ['01', '03'], true) && $venta->estado_factura === 'pendiente';

        if (! $esDocumentoInterno && ! $noEnviadoAunSunat) {
            return back()->with('error',
                'Este comprobante ya fue enviado a SUNAT: no se puede anular directamente. '.
                'Genera una Nota de Crédito con motivo "Anulación de la operación" desde el listado.'
            );
        }

        DB::transaction(function () use ($request, $venta) {
            // Las ventas viejas tienen almacen_id=null (nunca descontaron
            // stock, no hay nada que devolver acá) — se saltan limpio.
            if ($venta->almacen_id) {
                $venta->loadMissing('detalles');

                foreach ($venta->detalles as $detalle) {
                    if ($detalle->producto_id === null) {
                        continue;
                    }

                    $fila = StockAlmacen::lockForUpdate()->firstOrCreate(
                        ['producto_id' => $detalle->producto_id, 'almacen_id' => $venta->almacen_id],
                        ['stock' => 0]
                    );

                    $anterior = (int) $fila->stock;
                    $nuevo = $anterior + (int) $detalle->cantidad;
                    $fila->update(['stock' => $nuevo]);

                    MovimientoAlmacen::create([
                        'producto_id' => $detalle->producto_id,
                        'almacen_id' => $venta->almacen_id,
                        'tipo' => 'entrada',
                        'cantidad' => (int) $detalle->cantidad,
                        'stock_anterior' => $anterior,
                        'stock_nuevo' => $nuevo,
                        'motivo' => "Anulación de venta {$venta->n_seri}-{$venta->n_comp}",
                        'referencia' => $venta->numero_venta,
                        'usuario_id' => $request->user()->id,
                    ]);

                    Producto::find($detalle->producto_id)?->recalcularStock();
                }
            }

            $venta->update(['estado' => 'cancelada']);
        });

        return back()->with('mensaje', "Comprobante {$venta->n_seri}-{$venta->n_comp} anulado.");
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

    /** El original borra la venta; aquí se conserva el detalle asociado. */
    public function destroy(Venta $venta): RedirectResponse
    {
        $comprobante = "{$venta->n_seri}-{$venta->n_comp}";

        DB::transaction(function () use ($venta) {
            $venta->detalles()->delete();
            $venta->delete();
        });

        return back()->with('mensaje', "Comprobante {$comprobante} eliminado.");
    }

    /** Vista imprimible del comprobante. */
    /** Vista imprimible del comprobante, con el desglose de productos y el monto en letras. */
    public function comprobante(Venta $venta, NumeroALetras $numeroALetras): View
    {
        $venta->load(['detalles.producto:id,presentacion', 'guias', 'ventaOrigen', 'usuario']);

        // La Cotización no es un comprobante SUNAT: usa un formato propio,
        // más simple, pensado para enviarse al cliente antes de la venta.
        if ($venta->tipcomp === 'COT') {
            return view('admin.ventas.cotizacion', [
                'venta' => $venta,
                'tipos' => self::TIPOS,
                'cuentasBancarias' => CuentaBancaria::orderBy('id')->get(),
            ]);
        }

        $moneda = $venta->moneda === 'USD' ? 'DÓLARES AMERICANOS' : 'SOLES';

        return view('admin.ventas.comprobante', [
            'venta' => $venta,
            'tipos' => self::TIPOS,
            'montoLetras' => $numeroALetras->convertir((float) $venta->total, $moneda),
            'diasCredito' => $venta->fecha && $venta->fecha_vencimiento
                ? $venta->fecha->diffInDays($venta->fecha_vencimiento)
                : null,
        ]);
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
     * el documento a mano se busca por ahí, y en último caso por razón social.
     * Así el comprobante nace enlazado y no hace falta vincularlo después.
     */
    private function fichaDelCliente(array $datos): ?Cliente
    {
        if (! empty($datos['cliente_id'])) {
            return Cliente::find($datos['cliente_id']);
        }

        $documento = preg_replace('/\D/', '', (string) ($datos['n_ruc'] ?? ''));

        if ($documento !== '') {
            $porDoc = Cliente::whereRaw("REGEXP_REPLACE(numero_documento, '[^0-9]', '') = ?", [$documento])->first();

            if ($porDoc) {
                return $porDoc;
            }
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
            'cliente_id'                   => ['nullable', 'integer', 'exists:clientes,id'],
            'condicion_pago'               => ['nullable', 'string', 'max:100'],
            'metodo_pago'                  => ['nullable', 'string', 'max:50'],
            'almacen_id'                   => ['nullable', 'integer', 'exists:almacenes,id'],
            'monto'                        => ['nullable', 'numeric', 'min:0'],
            'tipo_operacion'               => ['nullable', Rule::in(['gravada', 'exonerada', 'inafecta'])],
            'items'                        => ['nullable', 'array'],
            'items.*.producto_codigo'      => ['nullable', 'string', 'max:50'],
            'items.*.producto_nombre'      => ['nullable', 'string', 'max:255'],
            'items.*.cantidad'             => ['nullable', 'integer', 'min:0'],
            'items.*.precio_unitario'      => ['nullable', 'numeric', 'min:0'],
            'precios_incluyen_igv'         => ['nullable', 'boolean'],
            'origen_id'                    => ['nullable', 'integer', 'exists:ventas,id'],
        ]);
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
            'items.*.cantidad'         => ['nullable', 'integer', 'min:0'],
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
            $cantidad = (int) ($item['cantidad'] ?? 0);

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
    private function ajustarStockDelta(int $productoId, int $almacenId, int $delta, Venta $venta, Request $request, array &$avisosStock): void
    {
        $fila = StockAlmacen::lockForUpdate()->firstOrCreate(
            ['producto_id' => $productoId, 'almacen_id' => $almacenId],
            ['stock' => 0]
        );

        $anterior = (int) $fila->stock;
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
