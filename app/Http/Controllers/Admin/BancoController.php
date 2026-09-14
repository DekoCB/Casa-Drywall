<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BancoController extends Controller
{
    public function index(): View
    {
        return view('admin.finanzas.bancos', [
            'bancos' => Banco::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Banco::create($this->validar($request) + ['activo' => true]);

        return back()->with('mensaje', 'Banco registrado correctamente');
    }

    public function update(Request $request, Banco $banco): RedirectResponse
    {
        $banco->update($this->validar($request));

        return back()->with('mensaje', 'Banco actualizado correctamente');
    }

    public function alternarEstado(Banco $banco): RedirectResponse
    {
        $banco->update(['activo' => ! $banco->activo]);

        return back()->with('mensaje', $banco->activo ? "«{$banco->nombre}» activado" : "«{$banco->nombre}» desactivado");
    }

    public function destroy(Banco $banco): RedirectResponse
    {
        $banco->delete();

        return back()->with('mensaje', 'Banco eliminado correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
        ]);
    }
}
