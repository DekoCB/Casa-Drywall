<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpresaTransporte;
use App\Models\GuiaRemision;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\GeneradorCorrelativo;
use App\Services\Sunat\ApiGoEmisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuiaRemisionController extends Controller
{
    /**
     * Catálogo 20 de SUNAT (motivo de traslado) — el módulo original usaba
     * una lista de 6 textos libres elegidos a mano; estos son los códigos
     * reales que exige SUNAT, los mismos que ya expone el sistema de
     * facturación electrónica (API-GO).
     */
    public const MOTIVOS = [
        '01' => 'Venta',
        '02' => 'Compra',
        '03' => 'Venta con entrega a terceros',
        '04' => 'Traslado entre establecimientos de la misma empresa',
        '05' => 'Consignación',
        '06' => 'Devolución',
        '07' => 'Recojo de bienes transformados',
        '08' => 'Importación',
        '09' => 'Exportación',
        '13' => 'Otros',
        '14' => 'Venta sujeta a confirmación del comprador',
        '18' => 'Traslado de bienes para transformación',
        '19' => 'Traslado de bienes desde un centro de acopio',
    ];

    /** Modalidad de traslado (catálogo SUNAT): quién transporta la mercadería. */
    public const MODALIDADES = [
        '01' => 'Transporte público',
        '02' => 'Transporte privado',
    ];

    public function __construct(
        private readonly GeneradorCorrelativo $correlativo,
        private readonly ApiGoEmisionService $emisionSunat,
    ) {}

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $estado = $request->query('estado');

        $guias = GuiaRemision::query()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numero_guia', 'like', "%{$busqueda}%")
                        ->orWhere('cliente_nombre', 'like', "%{$busqueda}%")
                        ->orWhere('numero_venta', 'like', "%{$busqueda}%")
                        ->orWhere('placa_vehiculo', 'like', "%{$busqueda}%");
                });
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.guias.index', [
            'guias' => $guias,
            'busqueda' => $busqueda,
            'estadoSel' => $estado,
            'totalGuias' => GuiaRemision::count(),
            'delMes' => GuiaRemision::whereYear('fecha', now()->year)->whereMonth('fecha', now()->month)->count(),
            'motivos' => self::MOTIVOS,
            'empresas' => EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get(),
        ]);
    }

    /** Ubigeo fijo del local propio (ver config/rentaltech.php) — se precarga como punto de partida. */
    private function ubigeoPropio(): ?string
    {
        return config('rentaltech.empresa.ubigeo');
    }

    public function create(Request $request): View
    {
        // Permite arrancar la guía desde una venta existente.
        $venta = $request->filled('venta')
            ? Venta::with('detalles')->find($request->query('venta'))
            : null;

        return view('admin.guias.create', [
            'venta' => $venta,
            'motivos' => self::MOTIVOS,
            'modalidades' => self::MODALIDADES,
            'ubigeoPropio' => $this->ubigeoPropio(),
            'empresas' => EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get(),
            'productos' => Producto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'presentacion', 'peso']),
            'ventas' => Venta::where('estado', 'completada')->orderByDesc('fecha')->limit(200)
                ->get(['id', 'numero_venta', 'fecha', 'cliente_nombre', 'cliente_ruc', 'cliente_direccion', 'cliente_distrito', 'destino_entrega', 'empresa_transporte']),
        ]);
    }

    public function show(GuiaRemision $guia): View
    {
        return view('admin.guias.show', compact('guia'));
    }

    public function edit(GuiaRemision $guia): View
    {
        return view('admin.guias.edit', [
            'guia' => $guia,
            'motivos' => self::MOTIVOS,
            'modalidades' => self::MODALIDADES,
            'ubigeoPropio' => $this->ubigeoPropio(),
            'empresas' => EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get(),
            'productos' => Producto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'presentacion', 'peso']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guia = GuiaRemision::create($this->validar($request) + [
            'numero_guia' => $this->correlativo->guiaRemision(),
            'estado' => 'emitida',
            'usuario_id' => $request->user()->id,
        ]);

        // Igual que con Boleta/Factura: la guía queda guardada local sí o
        // sí; el registro ante SUNAT nunca bloquea esto. Si falla, el
        // motivo real queda visible en la propia guía (ver
        // ApiGoEmisionService::crearGuiaRemision()).
        $this->emisionSunat->crearGuiaRemision($guia);

        return redirect()->route('admin.guias.index')
            ->with('mensaje', "Guía de remisión {$guia->numero_guia} emitida");
    }

    public function update(Request $request, GuiaRemision $guia): RedirectResponse
    {
        $guia->update($this->validar($request));

        return redirect()->route('admin.guias.index')
            ->with('mensaje', "Guía {$guia->numero_guia} actualizada");
    }

    public function destroy(GuiaRemision $guia): RedirectResponse
    {
        $guia->update(['estado' => 'anulada']);

        return redirect()->route('admin.guias.index')->with('mensaje', "Guía {$guia->numero_guia} anulada");
    }

    /**
     * Reintenta el registro en API-GO de una guía que nunca llegó a
     * registrarse ahí (sin `api_go_document_id`) — mismo patrón que
     * `VentaController::reintentarRegistroSunat()`. Si el motivo del
     * rechazo fue un dato incorrecto (ej. ubigeo inválido), se corrige
     * editando la guía normalmente y después se reintenta.
     */
    public function reintentarRegistroSunat(GuiaRemision $guia): RedirectResponse
    {
        abort_if($guia->api_go_document_id, 404);

        $registrado = $this->emisionSunat->crearGuiaRemision($guia);

        return back()->with(
            $registrado ? 'mensaje' : 'error',
            $registrado
                ? 'Guía de remisión registrada correctamente en el sistema de facturación electrónica.'
                : ($guia->fresh()->nota_sunat ?: 'No se pudo registrar la guía. Intenta de nuevo en unos segundos.')
        );
    }

    /**
     * Envía la guía ya registrada a SUNAT. A diferencia de Boleta/Factura,
     * SUNAT procesa las Guías de Remisión de forma asíncrona — la
     * respuesta inmediata es un ticket, no la aceptación final (ver
     * `verificarEstadoSunat()` para consultarla después).
     */
    public function enviarSunat(GuiaRemision $guia): RedirectResponse
    {
        $enviado = $this->emisionSunat->enviarGuiaRemisionSunat($guia);

        return back()->with(
            $enviado ? 'mensaje' : 'error',
            $enviado
                ? 'Guía enviada a SUNAT — el estado final puede tardar unos minutos, usa "Verificar estado" para consultarlo.'
                : 'No se pudo enviar la guía a SUNAT. Revisa el detalle abajo.'
        );
    }

    /** Consulta ante SUNAT (vía API-GO) si el envío async ya terminó de procesarse. */
    public function verificarEstadoSunat(GuiaRemision $guia): RedirectResponse
    {
        $this->emisionSunat->verificarEstadoGuiaRemision($guia);

        return back()->with('mensaje', 'Estado actualizado: '.($guia->fresh()->estado_sunat ?? 'sin cambios'));
    }

    /** Exporta la guía a CSV compatible con Excel. */
    public function excel(GuiaRemision $guia): StreamedResponse
    {
        $nombre = 'GR-'.($guia->numero_guia ?: $guia->id).'.csv';

        return response()->streamDownload(function () use ($guia) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, ['Guía de Remisión', $guia->numero_guia]);
            fputcsv($salida, ['Fecha', $guia->fecha?->format('d/m/Y')]);
            fputcsv($salida, ['Fecha de traslado', $guia->fecha_traslado?->format('d/m/Y')]);
            fputcsv($salida, ['Motivo', $guia->motivo_traslado]);
            fputcsv($salida, ['Destinatario', $guia->cliente_nombre]);
            fputcsv($salida, ['RUC/DNI', $guia->cliente_ruc]);
            fputcsv($salida, ['Punto de partida', $guia->punto_partida]);
            fputcsv($salida, ['Punto de llegada', $guia->punto_llegada]);
            fputcsv($salida, ['Transportista', $guia->empresa_transporte]);
            fputcsv($salida, ['Placa', $guia->placa_vehiculo]);
            fputcsv($salida, ['Conductor', $guia->conductor_nombre]);
            fputcsv($salida, []);
            fputcsv($salida, ['Código', 'Descripción', 'Cantidad', 'Peso']);

            foreach ($guia->productos ?? [] as $producto) {
                fputcsv($salida, [
                    $producto['codigo'] ?? '',
                    $producto['nombre'] ?? $producto['descripcion'] ?? '',
                    $producto['cantidad'] ?? 0,
                    $producto['peso'] ?? '',
                ]);
            }

            fputcsv($salida, []);
            fputcsv($salida, ['Peso total', $guia->peso_total]);
            fputcsv($salida, ['Bultos', $guia->bultos]);

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Mismas reglas condicionales que `StoreDispatchGuideRequest` de
     * API-GO (transporte público exige datos del transportista; privado
     * exige conductor + vehículo) — para que una guía nunca llegue a
     * intentar registrarse con datos que SUNAT va a rechazar igual.
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'venta_id' => ['nullable', 'integer', 'exists:ventas,id'],
            'numero_venta' => ['nullable', 'string', 'max:40'],
            'fecha' => ['required', 'date'],
            'fecha_traslado' => ['nullable', 'date'],
            'cod_traslado' => ['required', Rule::in(array_keys(self::MOTIVOS))],
            'mod_traslado' => ['required', Rule::in(array_keys(self::MODALIDADES))],
            'cliente_nombre' => ['required', 'string', 'max:200'],
            // El destinatario necesita un documento real: sin RUC/DNI no
            // hay forma de resolverlo del lado de API-GO (ver
            // ApiGoEmisionService::resolverClienteApiGo()).
            'cliente_ruc' => ['required', 'string', 'max:20'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'cliente_distrito' => ['nullable', 'string', 'max:100'],
            'cliente_provincia' => ['nullable', 'string', 'max:100'],
            'cliente_departamento' => ['nullable', 'string', 'max:100'],
            'punto_partida' => ['required', 'string', 'max:255'],
            'partida_ubigeo' => ['required', 'digits:6'],
            'punto_llegada' => ['required', 'string', 'max:255'],
            'llegada_ubigeo' => ['required', 'digits:6'],
            'empresa_transporte' => ['required_if:mod_traslado,01', 'nullable', 'string', 'max:200'],
            'transportista_ruc' => ['required_if:mod_traslado,01', 'nullable', 'string', 'max:20'],
            'placa_vehiculo' => ['required_if:mod_traslado,02', 'nullable', 'string', 'max:20'],
            'licencia_conductor' => ['required_if:mod_traslado,02', 'nullable', 'string', 'max:20'],
            'conductor_dni' => ['required_if:mod_traslado,02', 'nullable', 'string', 'max:15'],
            'conductor_nombre' => ['required_if:mod_traslado,02', 'nullable', 'string', 'max:200'],
            'peso_total' => ['required', 'numeric', 'min:0.001'],
            'und_peso_total' => ['nullable', 'string', 'max:3'],
            'bultos' => ['required', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string'],
            'productos' => ['nullable', 'array'],
        ]);

        $datos['motivo_traslado'] = self::MOTIVOS[$datos['cod_traslado']] ?? null;
        $datos['und_peso_total'] = $request->input('und_peso_total') ?: 'KGM';

        return $datos;
    }
}
