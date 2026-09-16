<?php

namespace App\Services;

use App\Models\MovimientoAlmacen;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\StockAlmacen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Entradas de stock por Orden de Compra: registrar (o editar) una orden con
 * productos del catálogo, en un almacén elegido, suma esa cantidad al
 * Inventario automáticamente — antes una Orden de Compra no tocaba
 * `Producto`/`stock_almacen` en absoluto, su `productos` era pura foto en
 * JSON sin ningún efecto en el stock real.
 *
 * A diferencia de Merch (`Merch.stock` se recalcula SUMANDO sus
 * movimientos), `stock_almacen.stock` es un contador que otros flujos
 * (Ventas, ajustes manuales) también tocan directo — no se puede recalcular
 * desde cero solo con los movimientos de esta orden. Por eso sincronizar
 * REVERSA primero lo que sus movimientos viejos habían sumado y recién
 * después aplica los nuevos, en vez de solo borrar y recrear. Es
 * idempotente: sincronizar dos veces con los mismos datos no cambia nada.
 */
class InventarioOrdenCompra
{
    /** Estado de orden que anula la compra: no suma stock. */
    private const ESTADO_ANULADO = 'cancelado';

    /**
     * Deja las entradas de stock de la orden calcadas de sus líneas de
     * producto. Sin almacén elegido no se toca el stock (la orden puede
     * seguir siendo solo de líneas manuales, fuera del catálogo).
     */
    public function sincronizarOrden(OrdenCompra $orden): void
    {
        DB::transaction(function () use ($orden) {
            $anteriores = MovimientoAlmacen::where('orden_compra_id', $orden->id)->get();
            $afectados = $anteriores->pluck('producto_id')->all();

            foreach ($anteriores as $mov) {
                $this->ajustarStock((int) $mov->producto_id, (int) $mov->almacen_id, -$mov->cantidad);
            }
            MovimientoAlmacen::where('orden_compra_id', $orden->id)->delete();

            $lineas = ($this->anulada($orden) || ! $orden->almacen_id) ? [] : $this->lineas($orden);

            foreach ($lineas as $linea) {
                $stockAnterior = $this->ajustarStock($linea['producto_id'], (int) $orden->almacen_id, $linea['cantidad']);

                MovimientoAlmacen::create([
                    'producto_id' => $linea['producto_id'],
                    'almacen_id' => $orden->almacen_id,
                    'tipo' => 'entrada',
                    'cantidad' => $linea['cantidad'],
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockAnterior + $linea['cantidad'],
                    'motivo' => 'Compra registrada en la orden '.$orden->numero_orden,
                    'referencia' => $orden->numero_orden,
                    'orden_compra_id' => $orden->id,
                    'usuario_id' => auth()->id(),
                    'estado' => 'entregado',
                ]);

                $afectados[] = $linea['producto_id'];
            }

            $this->recalcularStock($afectados);
        });
    }

    /** Al borrar la orden se van con ella las entradas de stock que había generado. */
    public function eliminarOrden(OrdenCompra $orden): void
    {
        DB::transaction(function () use ($orden) {
            $anteriores = MovimientoAlmacen::where('orden_compra_id', $orden->id)->get();
            $afectados = $anteriores->pluck('producto_id')->all();

            foreach ($anteriores as $mov) {
                $this->ajustarStock((int) $mov->producto_id, (int) $mov->almacen_id, -$mov->cantidad);
            }
            MovimientoAlmacen::where('orden_compra_id', $orden->id)->delete();

            $this->recalcularStock($afectados);
        });
    }

    /**
     * Líneas de la orden que resuelven a un Producto real del catálogo (por
     * código) — una línea manual (código inventado, o de un proveedor sin
     * cargar en el catálogo) no tiene a qué producto sumarle stock, así que
     * se ignora, igual que ya hace Ventas con sus líneas sin `producto_id`.
     */
    public function lineas(OrdenCompra $orden): array
    {
        $productosOrden = is_array($orden->productos) ? $orden->productos : [];

        if ($productosOrden === []) {
            return [];
        }

        $codigos = collect($productosOrden)
            ->map(fn ($p) => trim((string) ($p['codigo'] ?? '')))
            ->filter()
            ->unique();

        $catalogo = Producto::whereIn('codigo', $codigos)->pluck('id', 'codigo');

        $lineas = [];

        foreach ($productosOrden as $linea) {
            $codigo = trim((string) ($linea['codigo'] ?? ''));
            $cantidad = (int) ($linea['cantidad'] ?? 0);

            if ($codigo === '' || ! $catalogo->has($codigo) || $cantidad < 1) {
                continue;
            }

            $lineas[] = [
                'producto_id' => (int) $catalogo[$codigo],
                'cantidad' => $cantidad,
            ];
        }

        return $lineas;
    }

    /**
     * Suma (o resta, si `$delta` es negativo) stock en un almacén, sin
     * dejarlo nunca negativo. Devuelve el stock ANTES de aplicar el delta,
     * para que el movimiento guarde su `stock_anterior` real.
     */
    private function ajustarStock(int $productoId, int $almacenId, int $delta): int
    {
        $fila = StockAlmacen::lockForUpdate()->firstOrCreate(
            ['producto_id' => $productoId, 'almacen_id' => $almacenId],
            ['stock' => 0]
        );

        $anterior = (int) $fila->stock;
        $fila->update(['stock' => max(0, $anterior + $delta)]);

        return $anterior;
    }

    private function recalcularStock(array $productoIds): void
    {
        foreach (array_unique(array_filter($productoIds)) as $id) {
            Producto::find($id)?->recalcularStock();
        }
    }

    private function anulada(OrdenCompra $orden): bool
    {
        return Str::lower(trim((string) $orden->estado)) === self::ESTADO_ANULADO;
    }
}
