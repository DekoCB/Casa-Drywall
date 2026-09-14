<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetodoPagoController extends Controller
{
    public function index(): View
    {
        return view('admin.finanzas.metodos-pago', [
            'metodos' => MetodoPago::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MetodoPago::create($this->validar($request) + ['activo' => true]);

        return back()->with('mensaje', 'Método de pago registrado correctamente');
    }

    public function update(Request $request, MetodoPago $metodos_pago): RedirectResponse
    {
        $metodos_pago->update($this->validar($request));

        return back()->with('mensaje', 'Método de pago actualizado correctamente');
    }

    public function alternarEstado(MetodoPago $metodos_pago): RedirectResponse
    {
        $metodos_pago->update(['activo' => ! $metodos_pago->activo]);

        return back()->with('mensaje', $metodos_pago->activo ? "«{$metodos_pago->nombre}» activado" : "«{$metodos_pago->nombre}» desactivado");
    }

    public function destroy(MetodoPago $metodos_pago): RedirectResponse
    {
        $metodos_pago->delete();

        return back()->with('mensaje', 'Método de pago eliminado correctamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
        ]);
    }
}
