<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El merch deja de ser un catálogo suelto y pasa a tener existencias.
 *
 * Las entradas llegan desde las órdenes de compra (bloque de merch de la orden)
 * y las salidas son las entregas a clientes. `merch.stock` es un acumulado que
 * siempre se recalcula desde `merch_movimientos`, que es la fuente de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch', function (Blueprint $tabla) {
            $tabla->integer('stock')->default(0)->after('precio');
        });

        // Líneas de merch de la orden: artículo, cantidad y costo unitario en soles.
        Schema::table('ordenes_compra', function (Blueprint $tabla) {
            $tabla->json('merch')->nullable()->after('productos');
        });

        Schema::create('merch_movimientos', function (Blueprint $tabla) {
            $tabla->increments('id');
            $tabla->unsignedInteger('merch_id');
            $tabla->string('tipo', 10);              // entrada | salida
            $tabla->integer('cantidad');
            $tabla->decimal('costo_unit', 10, 2)->nullable();
            $tabla->date('fecha');

            // Origen de una entrada: la orden de compra que la trajo.
            $tabla->unsignedInteger('orden_compra_id')->nullable();
            $tabla->string('numero_orden', 50)->nullable();

            // Destino de una salida: el cliente que recibió el merch.
            $tabla->unsignedInteger('cliente_id')->nullable();
            $tabla->string('cliente_nombre', 255)->nullable();

            $tabla->string('observaciones', 255)->nullable();
            $tabla->unsignedInteger('usuario_id')->nullable();
            $tabla->timestamp('created_at')->useCurrent();

            $tabla->index('merch_id');
            $tabla->index('tipo');
            $tabla->index('orden_compra_id');
            $tabla->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_movimientos');

        Schema::table('ordenes_compra', function (Blueprint $tabla) {
            $tabla->dropColumn('merch');
        });

        Schema::table('merch', function (Blueprint $tabla) {
            $tabla->dropColumn('stock');
        });
    }
};
