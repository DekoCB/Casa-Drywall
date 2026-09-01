<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos: órdenes de compra, tokens de edición pública y guías de remisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->increments('id');
            $table->string('numero_orden', 50)->nullable();
            $table->date('fecha')->nullable();
            $table->string('proveedor', 255)->nullable();
            $table->string('ruc', 20)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('nro_factura', 100)->nullable();
            $table->string('nro_guia', 100)->nullable();
            $table->string('ref_fecha', 20)->nullable();
            $table->string('empresa_transporte', 200)->nullable();
            $table->string('cliente_ref', 200)->default('');
            $table->string('vendedor', 100)->default('');
            $table->string('cod_vendedor', 20)->default('');
            $table->string('peso', 30)->nullable();
            $table->integer('bultos')->nullable();
            $table->decimal('tc', 10, 4)->nullable();
            $table->decimal('precio_venta', 10, 2)->nullable();
            $table->decimal('gasto_unit', 10, 2)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('condicion_pago', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('total_usd', 12, 2)->nullable();
            $table->decimal('total_soles', 12, 2)->nullable();
            $table->json('productos')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('numero_orden');
            $table->index('fecha');
        });

        Schema::create('orden_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('orden_id');
            $table->string('token', 64)->unique();
            $table->dateTime('expira_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('guias_remision', function (Blueprint $table) {
            $table->increments('id');
            $table->string('numero_guia', 30);
            $table->unsignedInteger('venta_id')->nullable();
            $table->string('numero_venta', 40)->nullable();
            $table->date('fecha');
            $table->date('fecha_traslado')->nullable();
            $table->string('motivo_traslado', 100)->default('VENTA');
            $table->string('cliente_nombre', 200)->nullable();
            $table->string('cliente_ruc', 20)->nullable();
            $table->string('cliente_direccion', 255)->nullable();
            $table->string('cliente_distrito', 100)->nullable();
            $table->string('cliente_provincia', 100)->nullable();
            $table->string('cliente_departamento', 100)->nullable();
            $table->string('punto_partida', 255)->nullable();
            $table->string('punto_llegada', 255)->nullable();
            $table->string('empresa_transporte', 200)->nullable();
            $table->string('transportista_ruc', 20)->nullable();
            $table->string('placa_vehiculo', 20)->nullable();
            $table->string('licencia_conductor', 20)->nullable();
            $table->string('conductor_nombre', 200)->nullable();
            $table->string('peso_total', 30)->nullable();
            $table->integer('bultos')->nullable();
            $table->text('observaciones')->nullable();
            $table->json('productos')->nullable();
            $table->string('estado', 20)->default('emitida');
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('numero_guia');
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guias_remision');
        Schema::dropIfExists('orden_tokens');
        Schema::dropIfExists('ordenes_compra');
    }
};
