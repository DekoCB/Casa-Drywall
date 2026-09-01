<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    /** El listado vive ahora en la pestaña «Almacenes» del catálogo. */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.productos.almacenes');
    }

    /** Activa o desactiva el almacén sin tocar el resto de sus datos. */
    public function alternarEstado(Almacen $almacen): RedirectResponse
    {
        $almacen->update(['activo' => ! $almacen->activo]);

        return back()->with(
            'mensaje',
            $almacen->activo ? "«{$almacen->nombre}» activado" : "«{$almacen->nombre}» desactivado"
        );
    }

    public function store(Request $request): RedirectResponse
    {
        // Un almacén recién creado nace activo salvo que se diga lo contrario.
        Almacen::create($this->validar($request) + ['activo' => true]);

        return back()->with('mensaje', 'Almacén registrada correctamente');
    }

    public function update(Request $request, Almacen $almacen): RedirectResponse
    {
        $almacen->update($this->validar($request));

        return back()->with('mensaje', 'Almacén actualizada correctamente');
    }

    public function destroy(Almacen $almacen): RedirectResponse
    {
        $almacen->delete();

        return back()->with('mensaje', 'Almacén eliminada correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }
}
