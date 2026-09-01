<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\View\View;

/**
 * Panel de secretaría: acceso a Productos, Facturas y Órdenes de Compra.
 */
class SecretariaController extends Controller
{
    public function index(): View
    {
        return view('secretaria.index', [
            'totalProductos' => Producto::activos()->count(),
            'stockBajo' => Producto::activos()->whereColumn('stock', '<=', 'stock_minimo')->count(),
            'ocPendientes' => OrdenCompra::whereRaw("LOWER(TRIM(estado)) = 'pendiente'")->count(),
            'facturasMes' => Venta::where('n_comp', '!=', '')
                ->whereYear('fecha', now()->year)
                ->whereMonth('fecha', now()->month)
                ->count(),
        ]);
    }
}
