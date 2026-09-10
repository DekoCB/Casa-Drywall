<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CargoController extends Controller
{
    public function index(): View
    {
        return view('admin.cargos.index', [
            'cargos' => Cargo::orderBy('nombre')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Cargo::create($this->validar($request));

        return back()->with('mensaje', 'Cargo registrado correctamente');
    }

    public function update(Request $request, Cargo $cargo): RedirectResponse
    {
        $cargo->update($this->validar($request, $cargo));

        return back()->with('mensaje', 'Cargo actualizado correctamente');
    }

    public function destroy(Cargo $cargo): RedirectResponse
    {
        $cargo->delete();

        return back()->with('mensaje', 'Cargo eliminado correctamente');
    }

    private function validar(Request $request, ?Cargo $cargo = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('cargos', 'nombre')->ignore($cargo?->id)],
        ]);
    }
}
