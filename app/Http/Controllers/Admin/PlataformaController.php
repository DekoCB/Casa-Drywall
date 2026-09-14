<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plataforma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlataformaController extends Controller
{
    public function index(): View
    {
        return view('admin.finanzas.plataformas', [
            'plataformas' => Plataforma::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plataforma::create($this->validar($request) + ['activo' => true]);

        return back()->with('mensaje', 'Plataforma registrada correctamente');
    }

    public function update(Request $request, Plataforma $plataforma): RedirectResponse
    {
        $plataforma->update($this->validar($request));

        return back()->with('mensaje', 'Plataforma actualizada correctamente');
    }

    public function alternarEstado(Plataforma $plataforma): RedirectResponse
    {
        $plataforma->update(['activo' => ! $plataforma->activo]);

        return back()->with('mensaje', $plataforma->activo ? "«{$plataforma->nombre}» activada" : "«{$plataforma->nombre}» desactivada");
    }

    public function destroy(Plataforma $plataforma): RedirectResponse
    {
        $plataforma->delete();

        return back()->with('mensaje', 'Plataforma eliminada correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
        ]);
    }
}
