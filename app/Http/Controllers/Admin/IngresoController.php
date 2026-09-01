<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingreso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngresoController extends Controller
{
    public function index(Request $request): View
    {
        $mes = (int) $request->query('mes', now()->month);
        $anio = (int) $request->query('anio', now()->year);

        $ingresos = Ingreso::whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $porTipo = Ingreso::selectRaw('tipo, SUM(monto) AS total, COUNT(*) AS n')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->get();

        return view('admin.ingresos.index', [
            'ingresos' => $ingresos,
            'porTipo' => $porTipo,
            'mes' => $mes,
            'anio' => $anio,
            'totalMes' => (float) $porTipo->sum('total'),
            'anios' => $this->aniosDisponibles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Ingreso::create($this->validar($request));

        return back()->with('mensaje', 'Ingreso registrado exitosamente');
    }

    public function update(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $ingreso->update($this->validar($request));

        return back()->with('mensaje', 'Ingreso actualizado exitosamente');
    }

    public function destroy(Ingreso $ingreso): RedirectResponse
    {
        $ingreso->delete();

        return back()->with('mensaje', 'Ingreso eliminado exitosamente');
    }

    private function aniosDisponibles(): array
    {
        $anios = Ingreso::selectRaw('DISTINCT YEAR(fecha) AS anio')->orderByDesc('anio')->pluck('anio')->all();

        return $anios ?: [now()->year];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0'],
            'metodo_pago' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
