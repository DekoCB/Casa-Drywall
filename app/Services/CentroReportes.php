<?php

namespace App\Services;

use App\Models\Cobranza;
use App\Models\Producto;
use App\Models\VentaDetalle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cálculos de los reportes "avanzados" del Centro de Reportes. Cada método
 * devuelve la misma forma — `resumen`/`items` (para la vista HTML, con
 * datos ricos) y `columnas`/`filas` (planos, para Excel/PDF) — así la
 * exportación nunca recalcula ni se desincroniza de lo que se ve en pantalla.
 */
class CentroReportes
{
    /**
     * Clasifica los productos vendidos en A (hasta 80% del ingreso
     * acumulado), B (80-95%) y C (95-100%). Se excluyen Cotizaciones
     * (`Venta::scopeSinCotizaciones`) y ventas canceladas/eliminadas.
     */
    public function analisisAbc(?string $desde, ?string $hasta, string $busqueda = ''): array
    {
        $desde = $desde ?: now()->startOfYear()->toDateString();
        $hasta = $hasta ?: now()->toDateString();
        $busqueda = trim($busqueda);

        $filas = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalle.venta_id')
            ->where('ventas.tipcomp', '!=', 'COT')
            ->where(fn ($q) => $q->whereNull('ventas.estado')->orWhereNotIn('ventas.estado', ['cancelada', 'eliminada']))
            ->whereDate('ventas.fecha', '>=', $desde)
            ->whereDate('ventas.fecha', '<=', $hasta)
            ->when($busqueda !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('venta_detalle.prod_nombre', 'like', "%{$busqueda}%")
                    ->orWhere('venta_detalle.prod_codigo', 'like', "%{$busqueda}%")
            ))
            ->groupBy('venta_detalle.prod_codigo', 'venta_detalle.prod_nombre')
            ->selectRaw('venta_detalle.prod_codigo as codigo, venta_detalle.prod_nombre as nombre')
            ->selectRaw('SUM(venta_detalle.cantidad) as cantidad, SUM(venta_detalle.subtotal) as ingreso')
            ->orderByDesc('ingreso')
            ->get();

        $totalIngreso = (float) $filas->sum('ingreso');
        $acumulado = 0.0;
        $conteo = ['A' => 0, 'B' => 0, 'C' => 0];

        $items = $filas->values()->map(function ($fila, $i) use (&$acumulado, $totalIngreso, &$conteo) {
            $ingreso = (float) $fila->ingreso;
            $pct = $totalIngreso > 0 ? $ingreso / $totalIngreso * 100 : 0;
            // Clasifica según el acumulado ANTES de este producto: así el
            // ítem que hace cruzar el 80% todavía cae en A, no en B.
            $previo = $acumulado;
            $acumulado += $pct;
            $clase = $previo < 80 ? 'A' : ($previo < 95 ? 'B' : 'C');
            $conteo[$clase]++;

            return [
                'n' => $i + 1,
                'codigo' => $fila->codigo ?: '—',
                'nombre' => $fila->nombre,
                'cantidad' => (int) $fila->cantidad,
                'ingreso' => round($ingreso, 2),
                'pct' => round($pct, 2),
                'acumulado' => round($acumulado, 2),
                'clase' => $clase,
            ];
        });

        return [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta, 'q' => $busqueda],
            'resumen' => [
                'A' => ['n' => $conteo['A'], 'etiqueta' => '80%'],
                'B' => ['n' => $conteo['B'], 'etiqueta' => '15%'],
                'C' => ['n' => $conteo['C'], 'etiqueta' => '5%'],
                'total' => round($totalIngreso, 2),
            ],
            'items' => $items,
            'columnas' => ['#', 'Código', 'Producto', 'Cant.', 'Ingreso (S/)', '%', '% Acum.', 'Clase'],
            'filas' => $items->map(fn ($f) => [
                $f['n'], $f['codigo'], $f['nombre'], $f['cantidad'],
                number_format($f['ingreso'], 2), $f['pct'].'%', $f['acumulado'].'%', $f['clase'],
            ])->all(),
        ];
    }

    /**
     * Cruza lo vendido en el periodo contra el stock actual. Sin tabla de
     * eventos de "cuándo se agotó": es una foto del periodo elegido, no un
     * historial. `estado` es una heurística simple (documentada), no una
     * fórmula financiera estándar.
     */
    public function rotacionInventario(?string $desde, ?string $hasta): array
    {
        $hasta = $hasta ?: now()->toDateString();
        $desde = $desde ?: now()->subMonths(3)->toDateString();
        $dias = max(1, (int) Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1);

        $vendidos = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalle.venta_id')
            ->whereNotNull('venta_detalle.producto_id')
            ->where('ventas.tipcomp', '!=', 'COT')
            ->where(fn ($q) => $q->whereNull('ventas.estado')->orWhereNotIn('ventas.estado', ['cancelada', 'eliminada']))
            ->whereDate('ventas.fecha', '>=', $desde)
            ->whereDate('ventas.fecha', '<=', $hasta)
            ->groupBy('venta_detalle.producto_id')
            ->selectRaw('venta_detalle.producto_id as producto_id, SUM(venta_detalle.cantidad) as vendido')
            ->get()
            ->pluck('vendido', 'producto_id');

        $items = Producto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'stock', 'stock_minimo'])
            ->map(function (Producto $p) use ($vendidos, $dias) {
                $vendido = (int) ($vendidos[$p->id] ?? 0);
                $stock = (int) $p->stock;
                $rotacion = $vendido > 0 ? round($vendido / max($stock, 1), 2) : 0.0;
                $diasStock = $vendido > 0 ? (int) round($stock / ($vendido / $dias)) : null;
                $estado = $vendido === 0 ? 'Baja' : ($rotacion < 1 ? 'Media' : 'Alta');

                return [
                    'codigo' => $p->codigo ?: '—',
                    'nombre' => $p->nombre,
                    'stock' => $stock,
                    'minimo' => (int) $p->stock_minimo,
                    'vendido' => $vendido,
                    'rotacion' => $rotacion,
                    'dias_stock' => $diasStock,
                    'estado' => $estado,
                ];
            })
            ->sortBy('rotacion')
            ->values();

        return [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'resumen' => [
                'baja' => $items->where('estado', 'Baja')->count(),
                'media' => $items->where('estado', 'Media')->count(),
                'alta' => $items->where('estado', 'Alta')->count(),
                'total' => $items->count(),
            ],
            'items' => $items,
            'columnas' => ['Código', 'Producto', 'Stock', 'Mín.', 'Vendido', 'Rotación', 'Días Stock', 'Estado'],
            'filas' => $items->map(fn ($f) => [
                $f['codigo'], $f['nombre'], $f['stock'], $f['minimo'], $f['vendido'],
                $f['rotacion'].'x', $f['dias_stock'] ?? '∞', $f['estado'],
            ])->all(),
        ];
    }

    /**
     * Aging de cuentas por cobrar por cliente, en tramos de 30 días. Los
     * tramos se calculan en PHP con `Cobranza::diasVencidos()` (no con
     * `whereRaw`/`DATEDIFF`): esa raíz ya rompió los tests en SQLite una vez
     * en `CentroNotificaciones`, no se repite el error aquí.
     */
    public function agingCuentasPorCobrar(): array
    {
        $cobranzas = Cobranza::pendientes()
            ->with('cliente:id,numero_documento')
            ->whereNotNull('cliente_nombre')
            ->get();

        $grupos = $cobranzas->groupBy(
            fn (Cobranza $c) => $c->cliente_id ? "id:{$c->cliente_id}" : 'nombre:'.$c->cliente_nombre
        );

        $items = $grupos->map(function (Collection $grupo) {
            $tramos = ['vigente' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_mas' => 0.0];

            foreach ($grupo as $c) {
                $dias = $c->vencimientoInvalido() ? 0 : ($c->diasVencidos() ?? 0);
                $monto = (float) $c->monto_pendiente;

                match (true) {
                    $dias <= 0 => $tramos['vigente'] += $monto,
                    $dias <= 30 => $tramos['d1_30'] += $monto,
                    $dias <= 60 => $tramos['d31_60'] += $monto,
                    $dias <= 90 => $tramos['d61_90'] += $monto,
                    default => $tramos['d90_mas'] += $monto,
                };
            }

            $primero = $grupo->first();

            return [
                'cliente' => $primero->cliente_nombre,
                'ruc' => $primero->cliente?->numero_documento ?: '—',
                'docs' => $grupo->count(),
                ...array_map(fn ($v) => round($v, 2), $tramos),
                'total' => round(array_sum($tramos), 2),
            ];
        })->sortByDesc('total')->values();

        return [
            'resumen' => [
                'clientes' => $items->count(),
                'total' => round((float) $items->sum('total'), 2),
                'vencido90' => round((float) $items->sum('d90_mas'), 2),
            ],
            'items' => $items,
            'columnas' => ['Cliente', 'RUC/DNI', 'Docs', 'Vigente', '1-30d', '31-60d', '61-90d', '+90d', 'Total'],
            'filas' => $items->map(fn ($f) => [
                $f['cliente'], $f['ruc'], $f['docs'],
                number_format($f['vigente'], 2), number_format($f['d1_30'], 2), number_format($f['d31_60'], 2),
                number_format($f['d61_90'], 2), number_format($f['d90_mas'], 2), number_format($f['total'], 2),
            ])->all(),
        ];
    }
}
