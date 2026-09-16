<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Pos\CajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Panel del rol Ventas: solo Punto de Venta, sin cotizaciones, boletas ni
 * facturas fuera del POS.
 */
class VentasController extends Controller
{
    public function __construct(private readonly CajaService $cajas) {}

    public function index(Request $request): View
    {
        $usuario = auth()->user();

        // "Vendido hoy" y el total por rango son del negocio completo (todo
        // canal, todo usuario) — antes solo contaban lo que esta usuaria
        // vendía por POS, y una venta de "Nueva Venta" o de otro vendedor
        // nunca aparecía acá, sin importar el día.
        $desde = $request->query('desde') ?: now()->toDateString();
        $hasta = $request->query('hasta') ?: now()->toDateString();

        $ventasHoy = $this->ventasVigentes()->whereDate('fecha', now()->toDateString())->get();
        $ventasRango = $this->ventasVigentes()->whereBetween('fecha', [$desde, $hasta])->get();

        return view('ventas.index', [
            'sesion' => $this->cajas->sesionAbiertaDe($usuario),
            'ventasHoy' => $ventasHoy->count(),
            'montoHoy' => $this->totalConSigno($ventasHoy),
            'desde' => $desde,
            'hasta' => $hasta,
            'nVentasRango' => $ventasRango->count(),
            'montoRango' => $this->totalConSigno($ventasRango),
            'desglosePorDia' => $this->desglosePorDia($ventasRango),
        ]);
    }

    /**
     * Mismo criterio de "venta vigente" que `VentaController::index()`: sin
     * Cotizaciones (son un presupuesto, no una venta) y sin las anuladas —
     * `scopeVigentes()` no sirve acá porque compara contra 'anulada' y
     * `anular()` en realidad guarda 'cancelada'.
     */
    private function ventasVigentes()
    {
        return Venta::sinCotizaciones()
            ->where(fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'eliminada']));
    }

    /** Una Nota de Crédito (07) resta lo vendido, no lo suma — mismo signo que usa el listado de Ventas. */
    private function totalConSigno(Collection $ventas): float
    {
        return (float) $ventas->sum(fn (Venta $v) => ($v->tipcomp === '07' ? -1 : 1) * (float) $v->total);
    }

    /**
     * Una fila por día dentro del rango (más reciente primero), mismo
     * criterio de "vigente" y mismo signo que el total agregado — para que
     * el desglose y el total de arriba siempre cuadren entre sí.
     */
    private function desglosePorDia(Collection $ventasDelRango): Collection
    {
        return $ventasDelRango
            ->groupBy(fn (Venta $v) => $v->fecha->toDateString())
            ->map(fn ($ventasDelDia, $fecha) => [
                'fecha' => $fecha,
                'n' => $ventasDelDia->count(),
                'monto' => $this->totalConSigno($ventasDelDia),
            ])
            ->sortByDesc('fecha')
            ->values();
    }
}
