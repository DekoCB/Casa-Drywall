<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoriaController extends Controller
{
    public function index(): View
    {
        return view('admin.categorias.index', [
            'categorias' => Categoria::orderBy('nombre')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Categoria::create($this->validar($request));

        return back()->with('mensaje', 'Categoría registrada correctamente');
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($this->validar($request));

        return back()->with('mensaje', 'Categoría actualizada correctamente');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        $categoria->delete();

        return back()->with('mensaje', 'Categoría eliminada correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
