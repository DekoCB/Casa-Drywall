<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tarjeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TarjetaController extends Controller
{
    public function index(): View
    {
        return view('admin.finanzas.tarjetas', [
            'tarjetas' => Tarjeta::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tarjeta::create($this->validar($request) + ['activo' => true]);

        return back()->with('mensaje', 'Tarjeta registrada correctamente');
    }

    public function update(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->update($this->validar($request));

        return back()->with('mensaje', 'Tarjeta actualizada correctamente');
    }

    public function alternarEstado(Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->update(['activo' => ! $tarjeta->activo]);

        return back()->with('mensaje', $tarjeta->activo ? "«{$tarjeta->nombre}» activada" : "«{$tarjeta->nombre}» desactivada");
    }

    public function destroy(Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->delete();

        return back()->with('mensaje', 'Tarjeta eliminada correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
        ]);
    }
}
