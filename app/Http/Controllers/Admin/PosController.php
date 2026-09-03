<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\VentaSuspendida;
use App\Services\Pos\CajaService;
use App\Services\Pos\PosVentaService;
use App\Services\Sunat\ApiGoEmisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly PosVentaService $ventas,
        private readonly CajaService $cajas,
        private readonly ApiGoEmisionService $emisionSunat,
    ) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('admin.pos.index', [
            'sesion' => $this->cajas->sesionAbiertaDe($usuario),
            'cajas' => Caja::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'almacenes' => Almacen::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tipos' => array_intersect_key(VentaController::TIPOS, array_flip(['03', '01', 'NV'])),
            'metodosPago' => config('rentaltech.metodos_pago', []),
            'esAdmin' => $usuario->esAdmin(),
            'suspendidas' => VentaSuspendida::where('usuario_id', $usuario->id)->orderByDesc('id')->get(),
        ]);
    }

    /** Búsqueda AJAX de productos con stock del almacén elegido — no el catálogo completo embebido. */
    public function buscarProductos(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));
        $almacenId = (int) $request->query('almacen_id', 0);

        if ($termino === '' || $almacenId <= 0) {
            return response()->json([]);
        }

        $productos = Producto::activos()
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('codigo', 'like', "%{$termino}%");
            })
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'codigo', 'nombre', 'presentacion', 'precio_venta']);

        $stockPorProducto = StockAlmacen::where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productos->pluck('id'))
            ->pluck('stock', 'producto_id');

        $resultado = $productos->map(fn (Producto $p) => [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'presentacion' => $p->presentacion,
            'precio_venta' => (float) $p->precio_venta,
            'stock' => (int) ($stockPorProducto[$p->id] ?? 0),
        ]);

        return response()->json($resultado->values());
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $venta = $this->ventas->procesar($request->all(), $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al procesar una venta del POS', [
                'usuario_id' => $request->user()->id,
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'No se pudo completar la venta. Intenta de nuevo.'], 500);
        }

        // Fuera de la transacción y con try/catch propio: si API-GO está
        // caído la venta ya quedó guardada y no debe bloquearse por esto.
        try {
            $this->emisionSunat->crearComprobante($venta);
        } catch (\Throwable $e) {
            Log::warning('Fallo al registrar el comprobante POS en API-GO', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'venta_id' => $venta->id,
            'numero' => "{$venta->n_seri}-{$venta->n_comp}",
            'comprobante_url' => route('admin.ventas.comprobante', $venta),
            'vuelto' => (float) $venta->vuelto,
        ]);
    }

    public function suspender(Request $request): JsonResponse
    {
        $suspendida = $this->ventas->suspender($request->all(), $request->user());

        return response()->json(['id' => $suspendida->id]);
    }

    public function listaSuspendidas(Request $request): JsonResponse
    {
        return response()->json(
            VentaSuspendida::where('usuario_id', $request->user()->id)->orderByDesc('id')->get()
        );
    }

    public function recuperar(VentaSuspendida $ventaSuspendida): JsonResponse
    {
        return response()->json($ventaSuspendida);
    }

    public function eliminarSuspendida(VentaSuspendida $ventaSuspendida): JsonResponse
    {
        $ventaSuspendida->delete();

        return response()->json(['ok' => true]);
    }
}
