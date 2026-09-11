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
     * un acumulado aparte).
     */
    public function kardex(Producto $producto, ?string $desde, ?string $hasta): array
    {
        $movimientos = MovimientoAlmacen::where('producto_id', $producto->id)
            ->with('almacen:id,nombre', 'usuario:id,username')
            ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $items = $movimientos->map(fn (MovimientoAlmacen $m) => [
            'fecha' => $m->created_at,
            'almacen' => $m->almacen?->nombre ?? '—',
            'tipo' => $m->tipo,
            'cantidad' => $m->cantidad,
            'stock_anterior' => $m->stock_anterior,
            'stock_nuevo' => $m->stock_nuevo,
            'motivo' => $m->motivo ?: ($m->referencia ?: '—'),
            'usuario' => $m->usuario?->username ?? '—',
        ]);

        return [
            'producto' => $producto,
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'resumen' => [
                'movimientos' => $items->count(),
                'stock_actual' => (int) $producto->stock,
            ],
            'items' => $items,
            'columnas' => ['Fecha', 'Almacén', 'Tipo', 'Cantidad', 'Stock Ant.', 'Stock Nuevo', 'Motivo', 'Usuario'],
            'filas' => $items->map(fn (array $f) => [
                $f['fecha']->format('d/m/Y H:i'), $f['almacen'], ucfirst($f['tipo']), $f['cantidad'],
                $f['stock_anterior'], $f['stock_nuevo'], $f['motivo'], $f['usuario'],
            ])->all(),
        ];
    }

    /**
     * Kardex Valorizado: una fila por producto, con su costo y el valor
     * total que representa en stock — en vez del historial de movimientos
     * de un solo producto. Usa el costo ACTUAL del producto
     * (`precio_compra`) como "costo ponderado": no hay costo por compra
     * guardado en el historial para calcular un promedio ponderado real,
     * misma simplificación documentada que ya usaba el Kardex valorizado
     * por movimiento.
     */
    public function kardexValorizadoTodos(?int $categoriaId, ?int $marcaId, string $busqueda = ''): array
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

        $items = $productos->map(function (Producto $p) {
            $costoPonderado = (float) $p->precio_compra;
            $stock = (int) $p->stock;

            return [
                'codigo' => $p->codigo ?: '—',
                'nombre' => $p->nombre,
                'categoria' => $p->categoria?->nombre ?? '—',
                'marca' => $p->marca?->nombre ?? '—',
                'unidad' => $p->presentacion ?: '—',
                'stock' => $stock,
                'costo_ponderado' => round($costoPonderado, 2),
                'costo_producto' => round($stock * $costoPonderado, 2),
            ];
        });

        return [
            'filtros' => ['categoria' => $categoriaId, 'marca' => $marcaId, 'q' => $busqueda],
            'resumen' => [
                'productos' => $items->count(),
                'unidades' => (int) $items->sum('stock'),
                'valor_total' => round((float) $items->sum('costo_producto'), 2),
            ],
            'items' => $items,
            'columnas' => ['Código', 'Producto', 'Categoría', 'Marca', 'Unidad', 'Stock', 'Costo Ponderado', 'Costo de Producto'],
            'filas' => $items->map(fn ($f) => [
                $f['codigo'], $f['nombre'], $f['categoria'], $f['marca'], $f['unidad'], $f['stock'],
                number_format($f['costo_ponderado'], 2), number_format($f['costo_producto'], 2),
            ])->all(),
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

        $items = $productos->map(function (Producto $p) {
            $costo = (float) $p->precio_compra;
            $venta = (float) $p->precio_venta;
            $utilidad = $venta - $costo;

            return [
                'codigo' => $p->codigo ?: '—',
                'nombre' => $p->nombre,
                'categoria' => $p->categoria?->nombre ?? '—',
                'marca' => $p->marca?->nombre ?? '—',
                'stock' => (int) $p->stock,
                'minimo' => (int) $p->stock_minimo,
                'costo' => $costo,
                'precio_venta' => $venta,
                'utilidad' => round($utilidad, 2),
                // Margen sobre el precio de venta (no sobre el costo): 0 si
                // el producto todavía no tiene precio de venta cargado.
                'utilidad_pct' => $venta > 0 ? round($utilidad / $venta * 100, 1) : 0.0,
                'valor' => round((int) $p->stock * $costo, 2),
            ];
        });

        return [
            'filtros' => ['categoria' => $categoriaId, 'marca' => $marcaId, 'q' => $busqueda],
            'resumen' => [
                'productos' => $items->count(),
                'unidades' => (int) $items->sum('stock'),
                'valor_total' => round((float) $items->sum('valor'), 2),
                // Utilidad potencial de vender todo el stock actual al precio de venta.
                'utilidad_potencial' => round((float) $items->sum(fn ($f) => $f['stock'] * $f['utilidad']), 2),
            ],
            'items' => $items,
            'columnas' => ['Código', 'Producto', 'Categoría', 'Marca', 'Stock', 'Mín.', 'Costo Unit.', 'Precio Venta', 'Utilidad Unit.', 'Utilidad %', 'Valor'],
            'filas' => $items->map(fn ($f) => [
                $f['codigo'], $f['nombre'], $f['categoria'], $f['marca'], $f['stock'], $f['minimo'],
                number_format($f['costo'], 2), number_format($f['precio_venta'], 2),
                number_format($f['utilidad'], 2), $f['utilidad_pct'].'%', number_format($f['valor'], 2),
            ])->all(),
        ];
    }
}
