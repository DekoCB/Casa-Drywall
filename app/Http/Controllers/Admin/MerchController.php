<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Merch;
use App\Models\MerchMovimiento;
use App\Services\MerchInventario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchController extends Controller
{
    public function __construct(private readonly MerchInventario $inventario) {}

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));

        $items = Merch::query()
            ->when($busqueda !== '', fn ($q) => $q->where('nombre', 'like', "%{$busqueda}%"))
            ->when($categoria !== '', fn ($q) => $q->where('categoria', $categoria))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.merch.index', [
            'items' => $items,
            'busqueda' => $busqueda,
            'categoria' => $categoria,
            'clientes' => Cliente::orderBy('nombres')->get(['id', 'nombres', 'nombre_empresa', 'numero_documento']),
        ]);
    }

    /**
     * Historial de entradas y salidas. El filtro por orden permite llegar
     * desde la orden de compra a lo que ingresó por ella.
     */
    public function movimientos(Request $request): View
    {
        $merchId = (int) $request->query('merch', 0);
        $ordenId = (int) $request->query('orden', 0);
        $tipo = $request->query('tipo');

        $movimientos = MerchMovimiento::query()
            ->with('merch:id,nombre')
            ->when($merchId > 0, fn ($q) => $q->where('merch_id', $merchId))
            ->when($ordenId > 0, fn ($q) => $q->where('orden_compra_id', $ordenId))
            ->when(in_array($tipo, ['entrada', 'salida'], true), fn ($q) => $q->where('tipo', $tipo))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.merch.movimientos', [
            'movimientos' => $movimientos,
            'articulos' => Merch::orderBy('nombre')->get(['id', 'nombre']),
            'merchSel' => $merchId,
            'ordenSel' => $ordenId,
            'tipoSel' => in_array($tipo, ['entrada', 'salida'], true) ? $tipo : '',
        ]);
    }

    /** Entrega de merch a un cliente: descuenta stock y deja el registro. */
    public function entregar(Request $request, Merch $merch): RedirectResponse
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'cliente_nombre' => ['required_without:cliente_id', 'nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        if ($datos['cantidad'] > $merch->stock) {
            return back()->withErrors([
                'cantidad' => "Solo quedan {$merch->stock} unidad(es) de {$merch->nombre}.",
            ]);
        }

        // Si eligieron un cliente del listado, su nombre manda sobre lo escrito.
        if (! empty($datos['cliente_id'])) {
            $cliente = Cliente::find($datos['cliente_id']);
            $datos['cliente_nombre'] = $cliente?->nombre_empresa ?: $cliente?->nombres;
        }

        $this->inventario->registrarEntrega($merch, $datos);

        return redirect()->route('admin.merch.index')
            ->with('mensaje', "Entrega registrada: {$datos['cantidad']} × {$merch->nombre}");
    }

    /** Anula una entrega y devuelve las unidades al stock. */
    public function anularMovimiento(MerchMovimiento $movimiento): RedirectResponse
    {
        if ($movimiento->tipo === 'entrada') {
            return back()->withErrors([
                'movimiento' => 'Las entradas se corrigen desde la orden de compra que las generó.',
            ]);
        }

        $this->inventario->eliminarMovimiento($movimiento);

        return back()->with('mensaje', 'Entrega anulada; el stock volvió a su valor anterior');
    }

    public function store(Request $request): RedirectResponse
    {
        Merch::create($this->validar($request));

        return redirect()->route('admin.merch.index')->with('mensaje', 'Merch registrado exitosamente');
    }

    public function update(Request $request, Merch $merch): RedirectResponse
    {
        $merch->update($this->validar($request));

        return redirect()->route('admin.merch.index')->with('mensaje', 'Merch actualizado exitosamente');
    }

    public function destroy(Merch $merch): RedirectResponse
    {
        if ($merch->movimientos()->exists()) {
            return back()->withErrors([
                'merch' => "{$merch->nombre} tiene movimientos registrados; no se puede eliminar.",
            ]);
        }

        $merch->delete();

        return redirect()->route('admin.merch.index')->with('mensaje', 'Merch eliminado exitosamente');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
