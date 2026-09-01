<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpresaTransporte;
use App\Models\TarifaTransporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransporteController extends Controller
{
    public function index(): View
    {
        $empresas = EmpresaTransporte::where('estado', 'activo')->orderBy('nombre')->get();

        $tarifas = TarifaTransporte::where('tarifas_transporte.estado', 'activo')
            ->leftJoin('empresas_transporte as e', 'e.id', '=', 'tarifas_transporte.empresa_id')
            ->select('tarifas_transporte.*', 'e.nombre as empresa_nombre')
            ->orderBy('e.nombre')
            ->orderBy('tarifas_transporte.destino')
            ->get();

        // El precio sube con la distancia, así que ordenar los destinos por su
        // tarifa más barata los deja en el orden del recorrido.
        $destinos = $tarifas->groupBy('destino')
            ->map(fn ($grupo, $destino) => [
                'nombre' => $destino,
                'tarifas' => $grupo->values(),
                'desde' => (float) $grupo->min('precio_baldes'),
            ])
            ->sortBy('desde')
            ->values();

        return view('admin.transporte.index', [
            'empresas' => $empresas,
            'tarifas' => $tarifas,
            'destinos' => $destinos,

            'totalEmpresas' => $empresas->count(),
            'totalDestinos' => $destinos->count(),

            // Extremos de la escala, para dibujar la barra de cada parada.
            'tarifaMin' => (float) ($tarifas->min('precio_baldes') ?? 0),
            'tarifaMax' => (float) ($tarifas->max('precio_baldes') ?? 0),
        ]);
    }

    // ── Empresas ────────────────────────────────────────────────────────────

    /**
     * Alta de empresa. El mismo formulario puede traer su primera tarifa: una
     * empresa sin destinos no sirve de nada, así que se registran de una vez.
     */
    public function storeEmpresa(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'destino' => ['nullable', 'string', 'max:100'],
            'precio_baldes' => ['required_with:destino', 'nullable', 'numeric', 'min:0'],
            'precio_cajas' => ['required_with:destino', 'nullable', 'numeric', 'min:0'],
            'precio_cilindros' => ['required_with:destino', 'nullable', 'numeric', 'min:0'],
        ]);

        $empresa = EmpresaTransporte::create(['nombre' => $datos['nombre']]);

        $destino = trim((string) ($datos['destino'] ?? ''));

        if ($destino === '') {
            return back()->with('mensaje', "Empresa {$empresa->nombre} registrada");
        }

        TarifaTransporte::create([
            'empresa_id' => $empresa->id,
            'destino' => $destino,
            'precio_baldes' => $datos['precio_baldes'],
            'precio_cajas' => $datos['precio_cajas'],
            'precio_cilindros' => $datos['precio_cilindros'],
        ]);

        return back()->with('mensaje', "Empresa {$empresa->nombre} registrada con su tarifa a {$destino}");
    }

    public function updateEmpresa(Request $request, EmpresaTransporte $empresa): RedirectResponse
    {
        $empresa->update($request->validate([
            'nombre' => ['required', 'string', 'max:200'],
        ]));

        return back()->with('mensaje', 'Empresa de transporte actualizada');
    }

    public function destroyEmpresa(EmpresaTransporte $empresa): RedirectResponse
    {
        $empresa->update(['estado' => 'inactivo']);

        return back()->with('mensaje', 'Empresa de transporte desactivada');
    }

    // ── Tarifas ─────────────────────────────────────────────────────────────

    public function storeTarifa(Request $request): RedirectResponse
    {
        TarifaTransporte::create($this->validarTarifa($request));

        return back()->with('mensaje', 'Tarifa registrada');
    }

    public function updateTarifa(Request $request, TarifaTransporte $tarifa): RedirectResponse
    {
        $tarifa->update($this->validarTarifa($request));

        return back()->with('mensaje', 'Tarifa actualizada');
    }

    public function destroyTarifa(TarifaTransporte $tarifa): RedirectResponse
    {
        $tarifa->update(['estado' => 'inactivo']);

        return back()->with('mensaje', 'Tarifa desactivada');
    }

    private function validarTarifa(Request $request): array
    {
        return $request->validate([
            'empresa_id' => ['nullable', 'integer', 'exists:empresas_transporte,id'],
            'destino' => ['required', 'string', 'max:100'],
            'precio_baldes' => ['required', 'numeric', 'min:0'],
            'precio_cajas' => ['required', 'numeric', 'min:0'],
            'precio_cilindros' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
