<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta ahora una Orden de Compra no tocaba el Inventario en absoluto: sus
 * productos vivían solo como una foto en JSON. El negocio pidió que
 * registrar la compra sume stock automáticamente — para eso hace falta
 * saber A QUÉ almacén entra la mercadería (`ordenes_compra.almacen_id`,
 * igual que `ventas.almacen_id`) y poder identificar qué movimientos de
 * `movimientos_almacen` pertenecen a cada orden, para poder regenerarlos
 * limpio si la orden se edita (mismo `orden_compra_id` que ya usa
 * `merch_movimientos` para el mismo propósito con el catálogo de Merch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->unsignedInteger('almacen_id')->nullable()->after('proveedor');
        });

        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->unsignedInteger('orden_compra_id')->nullable()->after('usuario_id');
            $table->index('orden_compra_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->dropColumn('orden_compra_id');
        });

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('almacen_id');
        });
    }
};
