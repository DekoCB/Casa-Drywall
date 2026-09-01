<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailContacto;
use App\Models\EmpresaTransporte;
use App\Models\Merch;
use App\Models\OrdenCompra;
use App\Models\OrdenToken;
use App\Models\PedidoCliente;
use App\Models\Proveedor;
use App\Services\CatalogoKendall;
use App\Services\ExcelOrdenCompra;
use App\Services\GeneradorCorrelativo;
use App\Services\MerchInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrdenCompraController extends Controller
{
    /** Estados que puede tener una orden, en el orden del selector original. */
    public const ESTADOS = ['Pendiente', 'En Tránsito', 'Recibido', 'Cancelado'];

    /** Claves de sesión del lote de pedidos que se está registrando. */
    private const LOTE = 'oc_lote';
    private const LOTE_DATOS = 'oc_lote_datos';

    public function __construct(
        private readonly GeneradorCorrelativo $correlativo,
        private readonly MerchInventario $merch,
    ) {}

    /** Meses del selector, con el número que se usa como filtro. */
    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));
        $mes = (int) $request->query('mes', 0);

        $ordenes = OrdenCompra::query()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numero_orden', 'like', "%{$busqueda}%")
                        ->orWhere('proveedor', 'like', "%{$busqueda}%")
                        ->orWhere('ruc', 'like', "%{$busqueda}%")
                        ->orWhere('cliente_ref', 'like', "%{$busqueda}%")
                        ->orWhere('nro_factura', 'like', "%{$busqueda}%")
                        ->orWhere('productos', 'like', "%{$busqueda}%");
                });
            })
            ->when($estado !== '', fn ($q) => $q->whereRaw('LOWER(TRIM(estado)) = ?', [Str::lower($estado)]))
            ->when($mes >= 1 && $mes <= 12, fn ($q) => $q->whereMonth('fecha', $mes))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        // El listado se agrupa por día, de la fecha más reciente a la más antigua.
        $porDia = $ordenes->groupBy(fn (OrdenCompra $o) => $o->fecha?->format('Y-m-d') ?? '');

        // Margen y gastos salen del precio de venta y del gasto unitario de cada orden.
        $margenBruto = $ordenes->sum(fn (OrdenCompra $o) => (float) $o->precio_venta - (float) $o->total_soles);
        $gastosOp = $ordenes->sum(fn (OrdenCompra $o) => (float) $o->gasto_unit);

        // Al volver al listado la tanda se da por cerrada: sus órdenes quedan
        // marcadas para bajarlas juntas en un mismo libro.
        $lote = session()->pull(self::LOTE, []);
        session()->forget(self::LOTE_DATOS);

        $preseleccion = collect(explode(',', (string) $request->query('creada')))
            ->merge($lote)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return view('admin.ordenes-compra.index', [
            'preseleccion' => $preseleccion,
            'porDia' => $porDia,
            'nOrdenes' => $ordenes->count(),
            'busqueda' => $busqueda,
            'estadoSel' => $estado,
            'mesSel' => $mes,
            'estados' => self::ESTADOS,
            'meses' => self::MESES,
            'costoUsd' => (float) $ordenes->sum('total_usd'),
            'costoSoles' => (float) $ordenes->sum('total_soles'),
            'ventasSoles' => (float) $ordenes->sum('precio_venta'),
            'margenBruto' => $margenBruto,
            'gastosOp' => $gastosOp,
            'rentNeta' => $margenBruto - $gastosOp,
            'pedidos' => PedidoCliente::orderByDesc('fecha')->orderByDesc('id')->get(),
            'proveedores' => Proveedor::where('estado', 'activo')->orderBy('razon_social')->get(),
            'empresas' => EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get(),
            'contactos' => EmailContacto::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Guarda el N° de factura o de guía escrito directamente sobre la tabla.
     * Equivale a las acciones `update_factura` y `update_guia` del original.
     */
    public function actualizarDocumento(Request $request, OrdenCompra $orden): JsonResponse
    {
        $datos = $request->validate([
            'campo' => ['required', Rule::in(['nro_factura', 'nro_guia'])],
            'valor' => ['nullable', 'string', 'max:100'],
        ]);

        $orden->update([$datos['campo'] => trim((string) $datos['valor'])]);

        return response()->json(['ok' => true, 'valor' => $orden->{$datos['campo']}]);
    }

    public function create(CatalogoKendall $catalogo): View
    {
        // Se pueden registrar varias órdenes seguidas. Cada una es su propia
        // hoja de Excel; la tanda abierta se arrastra en sesión mientras el
        // usuario siga contestando que sí a "¿vas a registrar otra?".
        $lote = OrdenCompra::whereIn('id', session(self::LOTE, []))->orderBy('id')->get();

        return view('admin.ordenes-compra.create', [
            'lote'        => $lote,
            'previos'     => session(self::LOTE_DATOS, []),
            'estados'     => self::ESTADOS,
            'correlativo' => $this->correlativo->ordenCompra(),
            'catalogo'    => $catalogo->productos(),
            'lineas'      => CatalogoKendall::LINEAS,
            'bases'       => CatalogoKendall::BASES,
            'ejemplos'    => CatalogoKendall::EJEMPLOS,
            'emisor'      => config('rentaltech.emisor_oc'),
            'proveedores' => Proveedor::where('estado', 'activo')->orderBy('razon_social')->get(),
            'empresas'    => EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get(),
            'catalogoMerch' => Merch::orderBy('nombre')->get(),
        ]);
    }

    /**
     * Avisa si el número tecleado ya existe, como el `verificar_numero_orden`
     * del original: no bloquea, solo informa de la orden que lo ocupa.
     */
    public function verificarNumero(Request $request): JsonResponse
    {
        $numero = trim((string) $request->query('numero'));

        if ($numero === '') {
            return response()->json(['duplicado' => false]);
        }

        $orden = OrdenCompra::where('numero_orden', $numero)->first();

        if (! $orden) {
            return response()->json(['duplicado' => false]);
        }

        return response()->json([
            'duplicado' => true,
            'orden' => [
                'numero'    => $orden->numero_orden,
                'proveedor' => $orden->proveedor,
                'fecha'     => $orden->fecha?->format('d/m/Y'),
                'total_usd' => (float) $orden->total_usd,
            ],
        ]);
    }

    public function show(OrdenCompra $orden): View
    {
        return view('admin.ordenes-compra.show', compact('orden'));
    }

    public function edit(OrdenCompra $orden): View
    {
        return view('admin.ordenes-compra.edit', [
            'orden' => $orden,
            'estados' => self::ESTADOS,
            'proveedores' => Proveedor::where('estado', 'activo')->orderBy('razon_social')->get(),
            'empresas' => EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get(),
            'catalogoMerch' => Merch::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $numero = $request->filled('numero_orden')
            ? trim((string) $request->input('numero_orden'))
            : $this->correlativo->ordenCompra();

        $orden = OrdenCompra::create($this->conTotales($this->validar($request)) + ['numero_orden' => $numero]);

        $this->correlativo->consumirOrdenCompra($numero);

        // El merch de la orden entra al stock y genera su egreso de promoción.
        $this->merch->sincronizarOrden($orden);

        $lote = collect(session(self::LOTE, []))->push($orden->id)->unique()->values();

        // La orden queda guardada sí o sí. Recién después se pregunta si viene
        // otra detrás, con el formulario ya en blanco y los datos que se
        // repiten entre órdenes de la misma tanda conservados.
        session([
            self::LOTE => $lote->all(),
            self::LOTE_DATOS => [
                'fecha' => $orden->fecha?->format('Y-m-d'),
                'ref_fecha' => $orden->ref_fecha,
                'tc' => (string) $orden->tc,
                'cliente_ref' => $orden->cliente_ref,
                'empresa_transporte' => $orden->empresa_transporte,
            ],
        ]);

        return redirect()->route('admin.ordenes-compra.create')
            ->with('oc_guardada', [
                'numero' => $orden->numero_orden,
                'total_soles' => (float) $orden->total_soles + $this->merch->totalSoles($orden),
                'enTanda' => $lote->count(),
            ]);
    }

    public function update(Request $request, OrdenCompra $orden): RedirectResponse
    {
        $orden->update($this->conTotales($this->validar($request)));

        $this->merch->sincronizarOrden($orden);

        return redirect()->route('admin.ordenes-compra.index')
            ->with('mensaje', "Orden de compra {$orden->numero_orden} actualizada");
    }

    public function destroy(OrdenCompra $orden): RedirectResponse
    {
        $this->merch->eliminarOrden($orden);

        $orden->delete();

        return redirect()->route('admin.ordenes-compra.index')->with('mensaje', 'Orden de compra eliminada');
    }

    /**
     * Descarga el libro de Excel de una o varias órdenes, en el formato
     * elegido: `proveedor` (RT-PV-F-01) o `secretaria` (costos y margen).
     * Cada orden ocupa su propia hoja, igual que en el original.
     */
    public function excel(Request $request, ExcelOrdenCompra $generador): Response
    {
        $tipo = $request->query('tipo') === 'secretaria' ? 'secretaria' : 'proveedor';

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter();

        $ordenes = $ids->isNotEmpty()
            ? OrdenCompra::whereIn('id', $ids)->orderBy('id')->get()
            : OrdenCompra::orderByDesc('fecha')->orderByDesc('id')->get();

        abort_if($ordenes->isEmpty(), 404, 'No hay órdenes que exportar.');

        return response($generador->generar($ordenes, $tipo), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$generador->nombreArchivo($ordenes, $tipo).'"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /** Envía la orden por correo a los contactos seleccionados. */
    public function enviarCorreo(Request $request, OrdenCompra $orden): RedirectResponse
    {
        $datos = $request->validate([
            'destinatarios' => ['required', 'array', 'min:1'],
            'destinatarios.*' => ['email'],
            'asunto' => ['nullable', 'string', 'max:200'],
            'mensaje' => ['nullable', 'string'],
        ]);

        $asunto = $datos['asunto'] ?: "Orden de Compra {$orden->numero_orden} — Rental Tech SAC";

        Mail::raw(
            $datos['mensaje'] ?: "Adjuntamos la orden de compra {$orden->numero_orden}.",
            function ($mail) use ($datos, $asunto) {
                $mail->to($datos['destinatarios'])->subject($asunto);
            }
        );

        return back()->with('mensaje', 'Orden enviada a '.count($datos['destinatarios']).' destinatario(s)');
    }

    /** Crea un enlace temporal para que el proveedor edite la orden sin login. */
    public function generarToken(OrdenCompra $orden): JsonResponse
    {
        $token = OrdenToken::create([
            'orden_id' => $orden->id,
            'token' => Str::random(48),
            'expira_at' => now()->addDays(7),
        ]);

        return response()->json([
            'ok' => true,
            'url' => route('orden.publica', $token->token),
            'expira' => $token->expira_at->format('d/m/Y H:i'),
        ]);
    }

    /** Vista pública de edición, accesible solo con un token vigente. */
    public function editablePublica(string $token): View
    {
        $registro = OrdenToken::where('token', $token)
            ->where('expira_at', '>', now())
            ->firstOrFail();

        $orden = OrdenCompra::findOrFail($registro->orden_id);

        return view('publico.orden-editable', compact('orden', 'token'));
    }

    public function guardarPublica(Request $request, string $token): RedirectResponse
    {
        $registro = OrdenToken::where('token', $token)
            ->where('expira_at', '>', now())
            ->firstOrFail();

        $orden = OrdenCompra::findOrFail($registro->orden_id);

        // Desde el enlace público solo se permite confirmar datos logísticos.
        $orden->update($request->validate([
            'nro_factura' => ['nullable', 'string', 'max:100'],
            'nro_guia' => ['nullable', 'string', 'max:100'],
            'peso' => ['nullable', 'string', 'max:30'],
            'bultos' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]));

        return back()->with('mensaje', 'Datos actualizados. Gracias.');
    }

    /** Recalcula el total en soles a partir del total en dólares y el TC. */
    private function conTotales(array $datos): array
    {
        $totalUsd = (float) ($datos['total_usd'] ?? 0);
        $tc = (float) ($datos['tc'] ?? 0);

        if (empty($datos['total_soles']) && $totalUsd > 0 && $tc > 0) {
            $datos['total_soles'] = round($totalUsd * $tc, 2);
        }

        return $datos;
    }

    private function validar(Request $request): array
    {
        // El formulario manda las líneas serializadas en un campo oculto.
        foreach (['productos', 'merch'] as $campo) {
            if (is_string($request->input($campo))) {
                $request->merge([$campo => json_decode($request->input($campo), true) ?: []]);
            }
        }

        return $request->validate([
            'numero_orden' => ['nullable', 'string', 'max:50'],
            'fecha' => ['required', 'date'],
            'proveedor' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'nro_factura' => ['nullable', 'string', 'max:100'],
            'nro_guia' => ['nullable', 'string', 'max:100'],
            'ref_fecha' => ['nullable', 'string', 'max:20'],
            'empresa_transporte' => ['nullable', 'string', 'max:200'],
            'cliente_ref' => ['nullable', 'string', 'max:200'],
            'vendedor' => ['nullable', 'string', 'max:100'],
            'cod_vendedor' => ['nullable', 'string', 'max:20'],
            'peso' => ['nullable', 'string', 'max:30'],
            'bultos' => ['nullable', 'integer', 'min:0'],
            'tc' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'gasto_unit' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'string', 'max:50'],
            'condicion_pago' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string'],
            'total_usd' => ['nullable', 'numeric', 'min:0'],
            'total_soles' => ['nullable', 'numeric', 'min:0'],
            'productos' => ['nullable', 'array'],

            // Merch de la orden: se costea en soles, aparte del total en dólares.
            'merch' => ['nullable', 'array'],
            'merch.*.merch_id' => ['required', 'integer', 'exists:merch,id'],
            'merch.*.cantidad' => ['required', 'integer', 'min:1'],
            'merch.*.costo_unit' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
