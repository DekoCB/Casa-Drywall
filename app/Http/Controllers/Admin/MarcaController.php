<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarcaController extends Controller
{
    public function index(): View
    {
        return view('admin.marcas.index', [
            'marcas' => Marca::orderBy('nombre')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Marca::create($this->validar($request));

        return back()->with('mensaje', 'Marca registrada correctamente');
    }

    public function update(Request $request, Marca $marca): RedirectResponse
    {
        $marca->update($this->validar($request));

        return back()->with('mensaje', 'Marca actualizada correctamente');
    }

    public function destroy(Marca $marca): RedirectResponse
    {
        $marca->delete();

        return back()->with('mensaje', 'Marca eliminada correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
