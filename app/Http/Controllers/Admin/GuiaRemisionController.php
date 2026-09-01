<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpresaTransporte;
use App\Models\GuiaRemision;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\GeneradorCorrelativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuiaRemisionController extends Controller
{
    /** Motivos de traslado admitidos por SUNAT que usaba el módulo original. */
    public const MOTIVOS = ['VENTA', 'COMPRA', 'TRASLADO ENTRE ESTABLECIMIENTOS', 'CONSIGNACIÓN', 'DEVOLUCIÓN', 'OTROS'];

    public function __construct(private readonly GeneradorCorrelativo $correlativo) {}

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

    public function create(Request $request): View
    {
        // Permite arrancar la guía desde una venta existente.
        $venta = $request->filled('venta')
            ? Venta::with('detalles')->find($request->query('venta'))
            : null;

        return view('admin.guias.create', [
            'venta' => $venta,
            'motivos' => self::MOTIVOS,
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

    private function validar(Request $request): array
    {
        return $request->validate([
            'venta_id' => ['nullable', 'integer', 'exists:ventas,id'],
            'numero_venta' => ['nullable', 'string', 'max:40'],
            'fecha' => ['required', 'date'],
            'fecha_traslado' => ['nullable', 'date'],
            'motivo_traslado' => ['required', 'string', 'max:100'],
            'cliente_nombre' => ['required', 'string', 'max:200'],
            'cliente_ruc' => ['nullable', 'string', 'max:20'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'cliente_distrito' => ['nullable', 'string', 'max:100'],
            'cliente_provincia' => ['nullable', 'string', 'max:100'],
            'cliente_departamento' => ['nullable', 'string', 'max:100'],
            'punto_partida' => ['required', 'string', 'max:255'],
            'punto_llegada' => ['required', 'string', 'max:255'],
            'empresa_transporte' => ['nullable', 'string', 'max:200'],
            'transportista_ruc' => ['nullable', 'string', 'max:20'],
            'placa_vehiculo' => ['nullable', 'string', 'max:20'],
            'licencia_conductor' => ['nullable', 'string', 'max:20'],
            'conductor_nombre' => ['nullable', 'string', 'max:200'],
            'peso_total' => ['nullable', 'string', 'max:30'],
            'bultos' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'productos' => ['nullable', 'array'],
        ]);
    }
}
