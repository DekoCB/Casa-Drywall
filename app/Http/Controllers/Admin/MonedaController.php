<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Moneda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonedaController extends Controller
{
    public function index(): View
    {
        return view('admin.finanzas.monedas', [
            'monedas' => Moneda::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Moneda::create($this->validar($request) + ['activo' => true]);

        return back()->with('mensaje', 'Moneda registrada correctamente');
    }

    public function update(Request $request, Moneda $moneda): RedirectResponse
    {
        $moneda->update($this->validar($request));

        return back()->with('mensaje', 'Moneda actualizada correctamente');
    }

    public function alternarEstado(Moneda $moneda): RedirectResponse
    {
        $moneda->update(['activo' => ! $moneda->activo]);

        return back()->with('mensaje', $moneda->activo ? "«{$moneda->nombre}» activada" : "«{$moneda->nombre}» desactivada");
    }

    public function destroy(Moneda $moneda): RedirectResponse
    {
        $moneda->delete();

        return back()->with('mensaje', 'Moneda eliminada correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'codigo' => ['required', 'string', 'max:10'],
            'simbolo' => ['required', 'string', 'max:10'],
        ]);
    }
}
