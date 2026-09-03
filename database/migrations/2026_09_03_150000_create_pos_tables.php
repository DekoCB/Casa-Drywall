<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto de Venta: cajas físicas, sesiones de caja (apertura/cierre),
 * movimientos de caja (para el arqueo) y pagos por venta (permite pago
 * mixto). Ver app/Services/Pos/PosVentaService.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sesiones_caja', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('caja_id');
            $table->unsignedInteger('usuario_id');
            $table->decimal('monto_inicial', 10, 2)->default(0);
            $table->decimal('monto_final_esperado', 10, 2)->nullable();
            $table->decimal('monto_final_contado', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->string('estado', 20)->default('abierta'); // abierta | cerrada
            $table->text('observaciones')->nullable();
            $table->timestamp('abierta_en');
            $table->timestamp('cerrada_en')->nullable();

            $table->index('caja_id');
            $table->index('usuario_id');
            $table->index('estado');
        });

        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sesion_caja_id');
            $table->string('tipo', 20); // venta | ingreso | egreso | ajuste
            $table->string('metodo_pago', 30)->nullable(); // solo "Efectivo" cuenta para el arqueo físico
            $table->decimal('monto', 10, 2); // negativo = sale de caja (vuelto, egreso)
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedInteger('referencia_id')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('sesion_caja_id');
            $table->index('tipo');
        });

        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('venta_id');
            $table->string('metodo_pago', 30);
            $table->decimal('monto', 10, 2);
            $table->string('referencia', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('venta_id');
        });

        Schema::create('ventas_suspendidas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('usuario_id');
            $table->string('cliente_etiqueta', 150)->nullable();
            $table->decimal('total_referencial', 10, 2)->nullable();
            $table->json('datos');
            $table->timestamp('created_at')->useCurrent();

            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_suspendidas');
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('sesiones_caja');
        Schema::dropIfExists('cajas');
    }
};
