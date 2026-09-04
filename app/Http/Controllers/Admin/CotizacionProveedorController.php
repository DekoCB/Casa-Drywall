<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CotizacionProveedor;
use App\Models\Proveedor;
use App\Services\GeneradorCorrelativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Solicitud de precio a un proveedor, antes de generar la Orden de Compra. */
class CotizacionProveedorController extends Controller
{
    public function __construct(private readonly GeneradorCorrelativo $correlativo) {}

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));

        $cotizaciones = CotizacionProveedor::query()
            ->with('proveedor:id,razon_social,email')
            ->when($busqueda !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('numero', 'like', "%{$busqueda}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$busqueda}%"))
            ))
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.cotizaciones-proveedor.index', [
            'cotizaciones' => $cotizaciones,
            'busqueda' => $busqueda,
            'estadoSel' => $estado,
            'proveedores' => Proveedor::orderBy('razon_social')->get(['id', 'razon_social']),
            'abrirCrear' => $request->boolean('crear'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['numero'] = $this->correlativo->siguiente('cotizaciones_proveedor', 'numero', 'SC');
        $datos['usuario_id'] = $request->user()->id;

        CotizacionProveedor::create($datos);

        return redirect()->route('admin.cotizaciones-proveedor.index')->with('mensaje', 'Solicitud de cotización registrada.');
    }

    public function update(Request $request, CotizacionProveedor $cotizacionProveedor): RedirectResponse
    {
        $cotizacionProveedor->update($this->validar($request));

        return redirect()->route('admin.cotizaciones-proveedor.index')->with('mensaje', 'Solicitud actualizada.');
    }

    public function destroy(CotizacionProveedor $cotizacionProveedor): RedirectResponse
    {
        $cotizacionProveedor->delete();

        return redirect()->route('admin.cotizaciones-proveedor.index')->with('mensaje', 'Solicitud eliminada.');
    }

    /** Manda la solicitud al correo del proveedor, si tiene uno registrado. */
    public function enviar(CotizacionProveedor $cotizacionProveedor): RedirectResponse
    {
        $proveedor = $cotizacionProveedor->proveedor;

        if (! $proveedor?->email) {
            return back()->with('error', 'El proveedor no tiene un correo registrado.');
        }

        $items = collect($cotizacionProveedor->productos ?? [])
            ->map(fn ($p) => '- '.($p['cantidad'] ?? '').' x '.($p['descripcion'] ?? $p['nombre'] ?? ''))
            ->implode("\n");

        Mail::raw(
            "Solicitamos cotización para los siguientes productos:\n\n{$items}\n\n".($cotizacionProveedor->observaciones ?? ''),
            function ($mail) use ($proveedor, $cotizacionProveedor) {
                $mail->to($proveedor->email)->subject("Solicitud de cotización {$cotizacionProveedor->numero}");
            }
        );

        return back()->with('mensaje', "Solicitud enviada a {$proveedor->email}.");
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'productos' => ['nullable', 'string'],
            'estado' => ['required', Rule::in(['enviada', 'respondida', 'vencida'])],
            'observaciones' => ['nullable', 'string'],
        ]);

        $datos['productos'] = $this->itemsDesdeTexto($datos['productos'] ?? '');

        return $datos;
    }

    /** El textarea manda una línea por ítem; se guarda como JSON simple (mismo criterio que Órdenes de Compra). */
    private function itemsDesdeTexto(string $texto): array
    {
        return collect(preg_split('/\r?\n/', trim($texto)))
            ->filter()
            ->map(fn ($linea) => ['descripcion' => trim($linea)])
            ->values()
            ->all();
    }
}
