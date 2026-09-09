<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Pos\CajaService;
use Illuminate\View\View;

/**
 * Panel del rol Ventas: solo Punto de Venta, sin cotizaciones, boletas ni
 * facturas fuera del POS.
 */
class VentasController extends Controller
{
    public function __construct(private readonly CajaService $cajas) {}

    public function index(): View
    {
        $usuario = auth()->user();

        $ventasHoy = Venta::where('usuario_id', $usuario->id)
            ->where('canal', 'pos')
            ->where('estado', 'activa')
            ->whereDate('fecha', now()->toDateString());

        return view('ventas.index', [
            'sesion' => $this->cajas->sesionAbiertaDe($usuario),
            'ventasHoy' => (clone $ventasHoy)->count(),
            'montoHoy' => (clone $ventasHoy)->sum('total'),
        ]);
    }
}
