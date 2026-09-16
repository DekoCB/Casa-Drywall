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
    /**
     * Medios de pago que se muestran como bucket propio en el desglose —
     * el resto (Tarjeta, Transferencia bancaria, Depósito bancario, los
     * bancos fijos del POS como BCP/Interbank/BBVA, "Mixto", etc.) se
     * engloba en "Transferencia", pedido explícito del negocio.
     */
    private const BUCKETS_METODO_PAGO = ['Efectivo', 'Yape', 'Plin'];

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
        // whereDate() en vez de whereBetween(): 'fecha' es DATE, pero según
        // el driver puede llegar a comparar con la hora incluida (ej. en el
        // SQLite de los tests, 'fecha' se guarda como '2026-09-15 00:00:00'
        // y un whereBetween con $desde === $hasta nunca calzaba) — whereDate()
        // siempre compara solo la fecha, sin importar cómo se haya guardado.
        $ventasRango = $this->ventasVigentes()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get();

        return view('ventas.index', [
            'sesion' => $this->cajas->sesionAbiertaDe($usuario),
            'ventasHoy' => $ventasHoy->count(),
            'montoHoy' => $this->totalConSigno($ventasHoy),
            'desde' => $desde,
            'hasta' => $hasta,
            'nVentasRango' => $ventasRango->count(),
            'montoRango' => $this->totalConSigno($ventasRango),
            'desglosePorDia' => $this->desglosePorDia($ventasRango),
            'ventasPorMedioPago' => $this->desglosePorMedioPago($ventasRango),
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

    /** Efectivo/Yape/Plin quedan sueltos; todo el resto cae en "Transferencia" — ver BUCKETS_METODO_PAGO. */
    private function bucketMetodoPago(?string $metodo): string
    {
        $metodo = trim((string) $metodo);

        if ($metodo === '') {
            return 'Sin especificar';
        }

        return in_array($metodo, self::BUCKETS_METODO_PAGO, true) ? $metodo : 'Transferencia';
    }

    /**
     * Una fila por medio de pago, en orden fijo (Efectivo/Yape/Plin/
     * Transferencia siempre aparecen, aunque estén en cero, para que el
     * cajero vea siempre el mismo layout) — "Sin especificar" solo se
     * agrega si hay algo ahí (Notas de Crédito, que no tienen medio de
     * pago propio, o ventas de antes de que este campo existiera).
     */
    private function desglosePorMedioPago(Collection $ventasDelRango): Collection
    {
        $porBucket = $ventasDelRango->groupBy(fn (Venta $v) => $this->bucketMetodoPago($v->metodo_pago));

        return collect([...self::BUCKETS_METODO_PAGO, 'Transferencia', 'Sin especificar'])
            ->map(function (string $etiqueta) use ($porBucket) {
                $grupo = $porBucket->get($etiqueta, collect());

                return ['etiqueta' => $etiqueta, 'n' => $grupo->count(), 'monto' => $this->totalConSigno($grupo)];
            })
            ->filter(fn (array $fila) => $fila['etiqueta'] !== 'Sin especificar' || $fila['n'] > 0)
            ->values();
    }
}
