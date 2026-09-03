<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PedidoCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pedidos que los clientes hacen directamente (antes de convertirse en
 * venta). El modelo/tabla ya existían y se mostraban de forma read-only
 * dentro de Órdenes de Compra — este controller agrega el CRUD real que
 * faltaba, sin tocar esa vista de solo lectura.
 */
class PedidoController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $estado   = trim((string) $request->query('estado', ''));

        $pedidos = PedidoCliente::query()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('cliente_nombre', 'like', "%{$busqueda}%")
                        ->orWhere('ruc', 'like', "%{$busqueda}%")
                        ->orWhere('destino', 'like', "%{$busqueda}%");
                });
            })
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pedidos.index', [
            'pedidos' => $pedidos,
            'busqueda' => $busqueda,
            'estadoSel' => $estado,
            'abrirCrear' => $request->boolean('crear'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('archivo_pedido')) {
            $datos['archivo_pedido'] = $request->file('archivo_pedido')->store('pedidos', 'public');
        }

        PedidoCliente::create($datos);

        return redirect()->route('admin.pedidos.index')->with('mensaje', 'Pedido registrado.');
    }

    public function update(Request $request, PedidoCliente $pedido): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('archivo_pedido')) {
            if ($pedido->archivo_pedido) {
                Storage::disk('public')->delete($pedido->archivo_pedido);
            }
            $datos['archivo_pedido'] = $request->file('archivo_pedido')->store('pedidos', 'public');
        }

        $pedido->update($datos);

        return redirect()->route('admin.pedidos.index')->with('mensaje', 'Pedido actualizado.');
    }

    public function destroy(PedidoCliente $pedido): RedirectResponse
    {
        if ($pedido->archivo_pedido) {
            Storage::disk('public')->delete($pedido->archivo_pedido);
        }

        $pedido->delete();

        return redirect()->route('admin.pedidos.index')->with('mensaje', 'Pedido eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'cliente_nombre' => ['required', 'string', 'max:200'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'destino' => ['nullable', 'string', 'max:150'],
            'empresa_transporte' => ['nullable', 'string', 'max:200'],
            'productos' => ['nullable', 'string'],
            'total_soles' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'string', Rule::in(['Pendiente', 'En proceso', 'Entregado', 'Cancelado'])],
            'observaciones' => ['nullable', 'string'],
            'archivo_pedido' => ['nullable', 'file', 'max:8192'],
        ]);
    }
}
