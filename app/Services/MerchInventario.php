<?php

namespace App\Services;

use App\Models\Egreso;
use App\Models\Merch;
use App\Models\MerchMovimiento;
use App\Models\OrdenCompra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Existencias de merch: entradas por orden de compra, salidas por entrega al
 * cliente.
 *
 * `merch_movimientos` es la única fuente de verdad; `merch.stock` es un
 * acumulado que se recalcula desde ahí después de cada cambio. Las entradas de
 * una orden se regeneran completas cada vez que la orden se guarda, así editar
 * o borrar líneas nunca deja stock fantasma ni lo cuenta dos veces.
 */
class MerchInventario
{
    /** Estado de orden que anula la compra: no genera stock ni egreso. */
    private const ESTADO_ANULADO = 'cancelado';

    /** Marca del egreso generado por el merch de una orden. */
    public const ORIGEN_EGRESO = 'merch_orden_compra';

    /**
     * Deja las entradas y el egreso de la orden calcados de sus líneas de merch.
     * Es idempotente: llamarlo dos veces da el mismo resultado.
     */
    public function sincronizarOrden(OrdenCompra $orden): void
    {
        DB::transaction(function () use ($orden) {
            $afectados = MerchMovimiento::where('orden_compra_id', $orden->id)->pluck('merch_id')->all();

            MerchMovimiento::where('orden_compra_id', $orden->id)->delete();

            $lineas = $this->anulada($orden) ? [] : $this->lineas($orden);

            foreach ($lineas as $linea) {
                MerchMovimiento::create([
                    'merch_id' => $linea['merch_id'],
                    'tipo' => 'entrada',
                    'cantidad' => $linea['cantidad'],
                    'costo_unit' => $linea['costo_unit'],
                    'fecha' => $orden->fecha ?? now()->toDateString(),
                    'orden_compra_id' => $orden->id,
                    'numero_orden' => $orden->numero_orden,
                    'observaciones' => 'Compra registrada en la orden '.$orden->numero_orden,
                    'usuario_id' => auth()->id(),
                ]);

                $afectados[] = $linea['merch_id'];
            }

            $this->sincronizarEgreso($orden, $lineas);
            $this->recalcularStock($afectados);
        });
    }

    /** Al borrar la orden se van con ella sus entradas y su egreso. */
    public function eliminarOrden(OrdenCompra $orden): void
    {
        DB::transaction(function () use ($orden) {
            $afectados = MerchMovimiento::where('orden_compra_id', $orden->id)->pluck('merch_id')->all();

            MerchMovimiento::where('orden_compra_id', $orden->id)->delete();
            $this->egresoDeLaOrden($orden)->delete();

            $this->recalcularStock($afectados);
        });
    }

    /** Entrega de merch a un cliente: descuenta del stock. */
    public function registrarEntrega(Merch $merch, array $datos): MerchMovimiento
    {
        return DB::transaction(function () use ($merch, $datos) {
            $movimiento = MerchMovimiento::create([
                'merch_id' => $merch->id,
                'tipo' => 'salida',
                'cantidad' => (int) $datos['cantidad'],
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'cliente_id' => $datos['cliente_id'] ?? null,
                'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            $this->recalcularStock([$merch->id]);

            return $movimiento;
        });
    }

    /** Anula un movimiento suelto; las entradas de una orden se tocan desde la orden. */
    public function eliminarMovimiento(MerchMovimiento $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $merchId = $movimiento->merch_id;
            $movimiento->delete();
            $this->recalcularStock([$merchId]);
        });
    }

    /** Stock = todo lo que entró menos todo lo que salió. */
    public function recalcularStock(array $merchIds): void
    {
        foreach (array_unique(array_filter($merchIds)) as $id) {
            $saldo = (int) MerchMovimiento::where('merch_id', $id)
                ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE -cantidad END), 0) AS saldo")
                ->value('saldo');

            Merch::whereKey($id)->update(['stock' => $saldo]);
        }
    }

    /**
     * Líneas de merch de la orden, ya normalizadas y sin las que apuntan a un
     * artículo borrado. El costo va en soles, aparte del total en dólares de la
     * orden: el merch se compra local y no entra en el costeo por galón.
     */
    public function lineas(OrdenCompra $orden): array
    {
        $lineas = is_array($orden->merch) ? $orden->merch : [];

        if ($lineas === []) {
            return [];
        }

        $existentes = Merch::whereIn('id', array_filter(array_column($lineas, 'merch_id')))
            ->pluck('nombre', 'id');

        $normalizadas = [];

        foreach ($lineas as $linea) {
            $id = (int) ($linea['merch_id'] ?? 0);
            $cantidad = (int) ($linea['cantidad'] ?? 0);

            if (! $existentes->has($id) || $cantidad < 1) {
                continue;
            }

            $normalizadas[] = [
                'merch_id' => $id,
                'nombre' => $existentes[$id],
                'cantidad' => $cantidad,
                'costo_unit' => round((float) ($linea['costo_unit'] ?? 0), 2),
            ];
        }

        return $normalizadas;
    }

    /** Lo que costó el merch de una orden, en soles. */
    public function totalSoles(OrdenCompra $orden): float
    {
        return collect($this->lineas($orden))->sum(fn (array $l) => $l['cantidad'] * $l['costo_unit']);
    }

    /**
     * Un egreso por orden, tipo `promocion`, que es donde ya se venía anotando
     * el gasto en merch. Se actualiza o se borra según queden líneas o no.
     */
    private function sincronizarEgreso(OrdenCompra $orden, array $lineas): void
    {
        $monto = collect($lineas)->sum(fn (array $l) => $l['cantidad'] * $l['costo_unit']);

        if ($lineas === [] || $monto <= 0) {
            $this->egresoDeLaOrden($orden)->delete();

            return;
        }

        $detalle = collect($lineas)
            ->map(fn (array $l) => "{$l['cantidad']} {$l['nombre']}")
            ->implode(', ');

        $egreso = $this->egresoDeLaOrden($orden)->first();

        $datos = [
            'fecha' => $orden->fecha ?? now()->toDateString(),
            'tipo' => 'promocion',
            'categoria' => 'merch',
            'descripcion' => Str::limit("Merch orden {$orden->numero_orden}: {$detalle}", 250, ''),
            'monto' => round($monto, 2),
            'origen' => self::ORIGEN_EGRESO,
            'origen_id' => $orden->id,
            'usuario_id' => auth()->id(),
        ];

        $egreso ? $egreso->update($datos) : Egreso::create($datos);
    }

    private function egresoDeLaOrden(OrdenCompra $orden)
    {
        return Egreso::where('origen', self::ORIGEN_EGRESO)->where('origen_id', $orden->id);
    }

    private function anulada(OrdenCompra $orden): bool
    {
        return Str::lower(trim((string) $orden->estado)) === self::ESTADO_ANULADO;
    }
}
