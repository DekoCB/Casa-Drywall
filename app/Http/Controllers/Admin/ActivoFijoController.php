<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivoFijo;
use App\Models\Egreso;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registro de equipos/vehículos/maquinaria de la empresa. Comprar uno
 * también deja un Egreso (mismo criterio que ya usa Órdenes de Compra
 * recibidas, ver `SincronizadorEgresos::ordenesCompra()`), así el gasto
 * aparece en el módulo Egresos sin pantallas duplicadas.
 */
class ActivoFijoController extends Controller
{
    public const CATEGORIAS = ['Vehículo', 'Maquinaria', 'Equipo', 'Mobiliario', 'Otro'];

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));

        $activos = ActivoFijo::query()
            ->with('proveedor:id,razon_social')
            ->when($busqueda !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%")->orWhere('codigo', 'like', "%{$busqueda}%")
            ))
            ->when($categoria !== '', fn ($q) => $q->where('categoria', $categoria))
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.activos-fijos.index', [
            'activos' => $activos,
            'busqueda' => $busqueda,
            'categoriaSel' => $categoria,
            'categorias' => self::CATEGORIAS,
            'proveedores' => Proveedor::orderBy('razon_social')->get(['id', 'razon_social']),
            'abrirCrear' => $request->boolean('crear'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['usuario_id'] = $request->user()->id;

        DB::transaction(function () use ($datos) {
            $activo = ActivoFijo::create($datos);

            if ((float) $datos['costo'] > 0) {
                Egreso::create([
                    'fecha' => $datos['fecha_compra'],
                    'tipo' => 'Activo fijo',
                    'categoria' => 'activo_fijo',
                    'descripcion' => 'Compra de activo fijo: '.$datos['nombre'],
                    'monto' => $datos['costo'],
                    'usuario_id' => $datos['usuario_id'],
                    'origen' => 'activo_fijo',
                    'origen_id' => $activo->id,
                ]);
            }
        });

        return redirect()->route('admin.activos-fijos.index')->with('mensaje', 'Activo fijo registrado.');
    }

    public function update(Request $request, ActivoFijo $activoFijo): RedirectResponse
    {
        $activoFijo->update($this->validar($request));

        return redirect()->route('admin.activos-fijos.index')->with('mensaje', 'Activo fijo actualizado.');
    }

    public function destroy(ActivoFijo $activoFijo): RedirectResponse
    {
        DB::transaction(function () use ($activoFijo) {
            Egreso::where('origen', 'activo_fijo')->where('origen_id', $activoFijo->id)->delete();
            $activoFijo->delete();
        });

        return redirect()->route('admin.activos-fijos.index')->with('mensaje', 'Activo fijo eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'codigo' => ['nullable', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', Rule::in(self::CATEGORIAS)],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'fecha_compra' => ['required', 'date'],
            'costo' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in(['activo', 'de_baja'])],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
        ]);
    }
}
