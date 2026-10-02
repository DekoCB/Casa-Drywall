<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Varias rondas de carga de listas de productos (upsert por código, en
 * sesiones anteriores) terminaron creando una fila NUEVA en vez de
 * actualizar la existente para 10 códigos — dos productos reales con el
 * mismo código, cada uno con su propio precio de compra/venta. Las ventas
 * se fueron enlazando a la fila equivocada (confirmado comparando el
 * precio realmente cobrado contra cada candidato, y el precio de venta
 * vigente que confirmó el negocio para los casos donde no había ventas
 * todavía) — por eso el reporte de Utilidades mostraba un costo que no
 * correspondía al producto real, a veces hasta un margen negativo.
 *
 * `descartar => conservar`: la fila "conservar" es la que coincide con el
 * precio realmente vigente; todo lo de "descartar" (ventas, movimientos,
 * stock) se reasigna antes de borrar esa fila — no se pierde nada.
 */
return new class extends Migration
{
    private const PARES = [
        // codigo   descartar => conservar
        156 => 284, // 00057
        119 => 124, // 00020
        280 => 281, // 0000012
        101 => 148, // 00002
        175 => 118, // 00019
        282 => 283, // 0000013
        133 => 135, // 00036
        103 => 149, // 00004
        122 => 197, // 00023
        255 => 136, // 00037
    ];

    public function up(): void
    {
        foreach (self::PARES as $descartar => $conservar) {
            if (! DB::table('productos')->where('id', $descartar)->exists()) {
                continue; // ya consolidado (migración re-corrida o datos distintos a los de producción)
            }

            DB::transaction(function () use ($descartar, $conservar) {
                DB::table('venta_detalle')->where('producto_id', $descartar)->update(['producto_id' => $conservar]);
                DB::table('movimientos_almacen')->where('producto_id', $descartar)->update(['producto_id' => $conservar]);

                // Stock de la fila que se descarta: se suma al de la fila que
                // queda (mismo almacén) en vez de perderse — `stock_almacen`
                // tiene único (producto_id, almacen_id), así que si ya existe
                // fila para ese almacén en la que se conserva hay que sumar y
                // borrar, no se puede solo reapuntar el producto_id.
                $stocksDescartar = DB::table('stock_almacen')->where('producto_id', $descartar)->get();

                foreach ($stocksDescartar as $fila) {
                    $existente = DB::table('stock_almacen')
                        ->where('producto_id', $conservar)
                        ->where('almacen_id', $fila->almacen_id)
                        ->first();

                    if ($existente) {
                        DB::table('stock_almacen')
                            ->where('id', $existente->id)
                            ->update(['stock' => $existente->stock + $fila->stock]);
                        DB::table('stock_almacen')->where('id', $fila->id)->delete();
                    } else {
                        DB::table('stock_almacen')->where('id', $fila->id)->update(['producto_id' => $conservar]);
                    }
                }

                DB::table('productos')->where('id', $descartar)->delete();

                $stockTotal = (float) DB::table('stock_almacen')->where('producto_id', $conservar)->sum('stock');
                DB::table('productos')->where('id', $conservar)->update(['stock' => $stockTotal]);
            });
        }
    }

    /**
     * No reversible de verdad (se perdería cuál fila era cuál) — se deja
     * vacío a propósito, igual que cualquier limpieza de datos de este tipo.
     */
    public function down(): void {}
};
