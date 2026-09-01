<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $proveedores = Proveedor::query()
            ->where('estado', 'activo')
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('razon_social', 'like', "%{$busqueda}%")
                        ->orWhere('ruc', 'like', "%{$busqueda}%")
                        ->orWhere('contacto', 'like', "%{$busqueda}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.proveedores.index', [
            'proveedores' => $proveedores,
            'busqueda' => $busqueda,
            'totalActivos' => Proveedor::where('estado', 'activo')->count(),
            'conCredito' => Proveedor::where('estado', 'activo')->where('dias_credito', '>', 0)->count(),
            'promedioCredito' => (float) Proveedor::where('estado', 'activo')->avg('dias_credito'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Proveedor::create($this->validar($request));

        return redirect()->route('admin.proveedores.index')->with('mensaje', 'Proveedor registrado exitosamente');
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($this->validar($request));

        return redirect()->route('admin.proveedores.index')->with('mensaje', 'Proveedor actualizado exitosamente');
    }

    /** Baja lógica: el original solo marca el proveedor como inactivo. */
    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update(['estado' => 'inactivo']);

        return redirect()->route('admin.proveedores.index')->with('mensaje', 'Proveedor desactivado exitosamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'ruc' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'fecha_cumpleanos' => ['nullable', 'date'],
            'productos_suministra' => ['nullable', 'string'],
            'condiciones_pago' => ['required', Rule::in(['Contado', 'Crédito'])],
            'dias_credito' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
