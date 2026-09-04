<?php

namespace App\Services;

use App\Models\MovimientoAlmacen;
use App\Models\Producto;

/**
 * Reportes del módulo Inventario: Kardex (historial de un producto, con
 * saldo corrido) y el snapshot general de stock. Misma forma de retorno
 * que `CentroReportes` (`resumen`/`items`/`columnas`/`filas`) para
 * reutilizar `ExportadorReportes` y la plantilla PDF genérica sin cambios.
 */
class CentroInventario
{
    /**
     * Historial de movimientos de un producto. `stock_anterior`/
     * `stock_nuevo` ya quedan guardados por movimiento (no se recalcula
     * un acumulado aparte). En modo valorizado usa el costo ACTUAL del
     * producto (`precio_compra`) para todo el historial — no hay costo
     * por movimiento en la tabla, es una simplificación documentada.
     */
    public function kardex(Producto $producto, ?string $desde, ?string $hasta, bool $valorizado = false): array
    {
        $movimientos = MovimientoAlmacen::where('producto_id', $producto->id)
            ->with('almacen:id,nombre', 'usuario:id,username')
            ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $costo = (float) $producto->precio_compra;

        $items = $movimientos->map(function (MovimientoAlmacen $m) use ($costo, $valorizado) {
            $fila = [
                'fecha' => $m->created_at,
                'almacen' => $m->almacen?->nombre ?? '—',
                'tipo' => $m->tipo,
                'cantidad' => $m->cantidad,
                'stock_anterior' => $m->stock_anterior,
                'stock_nuevo' => $m->stock_nuevo,
                'motivo' => $m->motivo ?: ($m->referencia ?: '—'),
                'usuario' => $m->usuario?->username ?? '—',
            ];

            if ($valorizado) {
                $fila['costo_unitario'] = round($costo, 2);
                $fila['valor_movimiento'] = round($m->cantidad * $costo, 2);
                $fila['saldo_valorizado'] = round($m->stock_nuevo * $costo, 2);
            }

            return $fila;
        });

        $columnas = ['Fecha', 'Almacén', 'Tipo', 'Cantidad', 'Stock Ant.', 'Stock Nuevo', 'Motivo', 'Usuario'];
        $filaExport = fn (array $f) => [
            $f['fecha']->format('d/m/Y H:i'), $f['almacen'], ucfirst($f['tipo']), $f['cantidad'],
            $f['stock_anterior'], $f['stock_nuevo'], $f['motivo'], $f['usuario'],
        ];

        if ($valorizado) {
            $columnas = ['Fecha', 'Almacén', 'Tipo', 'Cantidad', 'Costo Unit.', 'Valor Mov.', 'Stock Nuevo', 'Saldo Valorizado'];
            $filaExport = fn (array $f) => [
                $f['fecha']->format('d/m/Y H:i'), $f['almacen'], ucfirst($f['tipo']), $f['cantidad'],
                number_format($f['costo_unitario'], 2), number_format($f['valor_movimiento'], 2),
                $f['stock_nuevo'], number_format($f['saldo_valorizado'], 2),
            ];
        }

        return [
            'producto' => $producto,
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'resumen' => [
                'movimientos' => $items->count(),
                'stock_actual' => (int) $producto->stock,
                'valor_actual' => $valorizado ? round($producto->stock * $costo, 2) : null,
            ],
            'items' => $items,
            'columnas' => $columnas,
            'filas' => $items->map($filaExport)->all(),
        ];
    }

    /** Snapshot de stock actual, con el valor a costo de compra. */
    public function reporteInventario(?int $categoriaId, ?int $marcaId, string $busqueda = ''): array
    {
        $busqueda = trim($busqueda);

        $productos = Producto::activos()
            ->with(['categoria:id,nombre', 'marca:id,nombre'])
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->when($marcaId, fn ($q) => $q->where('marca_id', $marcaId))
            ->when($busqueda !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%")->orWhere('codigo', 'like', "%{$busqueda}%")
            ))
            ->orderBy('nombre')
            ->get();

        $items = $productos->map(fn (Producto $p) => [
            'codigo' => $p->codigo ?: '—',
            'nombre' => $p->nombre,
            'categoria' => $p->categoria?->nombre ?? '—',
            'marca' => $p->marca?->nombre ?? '—',
            'stock' => (int) $p->stock,
            'minimo' => (int) $p->stock_minimo,
            'costo' => (float) $p->precio_compra,
            'valor' => round((int) $p->stock * (float) $p->precio_compra, 2),
        ]);

        return [
            'filtros' => ['categoria' => $categoriaId, 'marca' => $marcaId, 'q' => $busqueda],
            'resumen' => [
                'productos' => $items->count(),
                'unidades' => (int) $items->sum('stock'),
                'valor_total' => round((float) $items->sum('valor'), 2),
            ],
            'items' => $items,
            'columnas' => ['Código', 'Producto', 'Categoría', 'Marca', 'Stock', 'Mín.', 'Costo Unit.', 'Valor'],
            'filas' => $items->map(fn ($f) => [
                $f['codigo'], $f['nombre'], $f['categoria'], $f['marca'], $f['stock'], $f['minimo'],
                number_format($f['costo'], 2), number_format($f['valor'], 2),
            ])->all(),
        ];
    }
}
