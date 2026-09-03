<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\GeneradorCorrelativo;
use App\Services\NumeroALetras;
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
    ) {}

    /** Página de alta de comprobante: monto único o detalle de productos. */
    public function createFactura(): View
    {
        return view('admin.ventas.factura', [
            'tipos' => self::TIPOS,
            'clientes' => Cliente::orderBy('nombres')->get(['id', 'nombres', 'numero_documento']),
            'productos' => Producto::activos()->with(['categoria:id,nombre', 'marca:id,nombre'])->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'presentacion', 'categoria_id', 'marca_id', 'precio_venta', 'stock']),
        ]);
    }

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $mes      = trim((string) $request->query('mes', ''));   // formato YYYY-MM
        $desde    = trim((string) $request->query('desde', ''));
        $hasta    = trim((string) $request->query('hasta', ''));

        // Las canceladas y eliminadas quedan fuera del registro y de los totales,
        // el mismo criterio de `administrador/ventas.php`.
        $filtrada = Venta::query()
            ->where(function ($query) {
                $query->whereNull('estado')
                    ->orWhereNotIn('estado', ['cancelada', 'eliminada']);
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
        $datos = $this->validarFactura($request);
        $items = $this->itemsValidos($datos['items'] ?? []);

        $duplicado = Venta::where('tipcomp', $datos['tipcomp'])
            ->where('n_seri', $datos['n_seri'])
            ->where('n_comp', $datos['n_comp'])
            ->exists();

        if ($duplicado) {
            return back()->with('error', "Ya existe el comprobante {$datos['n_seri']}-{$datos['n_comp']}.");
        }

        if ($items !== []) {
            $subtotal = collect($items)->sum(
                fn (array $item) => (float) $item['cantidad'] * (float) $item['precio_unitario']
            );
            $igv = round($subtotal * config('rentaltech.igv'), 2);
            $importes = [
                'baseimp' => round($subtotal, 2),
                'igv' => $igv,
                'exonerado' => 0.0,
                'inafecto' => 0.0,
                'total' => round($subtotal + $igv, 2),
            ];
        } elseif ((float) ($datos['monto'] ?? 0) > 0 && ! empty($datos['tipo_operacion'])) {
            $importes = $this->conImportes(['monto' => $datos['monto'], 'tipo_operacion' => $datos['tipo_operacion']]);
        } else {
            return back()->withInput()->with('error', 'Ingresa un monto o agrega al menos un producto.');
        }

        $venta = DB::transaction(function () use ($request, $datos, $items, $importes) {
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

            foreach ($items as $item) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    // Se enlaza la ficha cuando el código existe, para que el
                    // comprobante pueda mostrar la unidad del producto.
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

        // Registro en el sistema de facturación electrónica (API-GO). Va
        // fuera de la transacción y protegido por try/catch: si el servicio
        // está caído la venta ya quedó guardada y no debe bloquearse por esto.
        try {
            $this->emisionSunat->crearComprobante($venta);
        } catch (\Throwable $e) {
            Log::warning('Fallo al registrar el comprobante en API-GO', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        // Se abre el comprobante recién generado, listo para imprimir o enviar.
        return redirect()->route('admin.ventas.comprobante', $venta)
            ->with('mensaje', "Comprobante {$venta->n_seri}-{$venta->n_comp} generado para {$venta->cliente_nombre}.");
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
            $subtotal = collect($items)->sum(
                fn (array $item) => (float) $item['cantidad'] * (float) $item['precio_unitario']
            );
            $igv = round($subtotal * config('rentaltech.igv'), 2);
            $importes = [
                'baseimp' => round($subtotal, 2),
                'igv' => $igv,
                'exonerado' => 0.0,
                'inafecto' => 0.0,
                'total' => round($subtotal + $igv, 2),
            ];
        } elseif ((float) ($datos['monto'] ?? 0) > 0 && ! empty($datos['tipo_operacion'])) {
            $importes = $this->conImportes(['monto' => $datos['monto'], 'tipo_operacion' => $datos['tipo_operacion']]);
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

        try {
            $this->emisionSunat->crearComprobante($venta);
        } catch (\Throwable $e) {
            Log::warning('Fallo al registrar la nota en API-GO', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
            ]);
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
        $venta->load(['detalles.producto:id,presentacion', 'guias', 'ventaOrigen']);

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

        $datos['baseimp']   = 0.0;
        $datos['igv']       = 0.0;
        $datos['exonerado'] = 0.0;
        $datos['inafecto']  = 0.0;

        if ($datos['tipo_operacion'] === 'gravada') {
            $datos['baseimp'] = round($monto, 2);
            $datos['igv']     = round($monto * config('rentaltech.igv'), 2);
        } elseif ($datos['tipo_operacion'] === 'exonerada') {
            $datos['exonerado'] = round($monto, 2);
        } else {
            $datos['inafecto'] = round($monto, 2);
        }

        $datos['total'] = round($datos['baseimp'] + $datos['igv'] + $datos['exonerado'] + $datos['inafecto'], 2);

        unset($datos['monto'], $datos['tipo_operacion']);

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
            'monto'                        => ['nullable', 'numeric', 'min:0'],
            'tipo_operacion'               => ['nullable', Rule::in(['gravada', 'exonerada', 'inafecta'])],
            'items'                        => ['nullable', 'array'],
            'items.*.producto_codigo'      => ['nullable', 'string', 'max:50'],
            'items.*.producto_nombre'      => ['nullable', 'string', 'max:255'],
            'items.*.cantidad'             => ['nullable', 'integer', 'min:0'],
            'items.*.precio_unitario'      => ['nullable', 'numeric', 'min:0'],
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
}
