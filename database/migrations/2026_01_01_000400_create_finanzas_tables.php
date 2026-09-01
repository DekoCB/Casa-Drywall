<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finanzas: cobranzas, ingresos, egresos, metas y costos mensuales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranzas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipo', 50)->nullable();
            $table->string('numero', 50);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('cliente_nombre', 255)->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->decimal('monto_pagado', 12, 2)->default(0);
            $table->decimal('monto_pendiente', 12, 2)->default(0);
            $table->string('estado', 30)->default('pendiente');
            $table->date('fecha_pago')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['numero', 'tipo']);
            $table->index('estado');
            $table->index('fecha_emision');
        });

        Schema::create('ingresos', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fecha');
            $table->string('tipo', 50)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->string('metodo_pago', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('fecha');
        });

        Schema::create('egresos', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fecha');
            $table->string('tipo', 50)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->unsignedInteger('venta_id')->nullable();
            $table->string('numero_venta', 50)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->unsignedInteger('almacen_id')->nullable();
            $table->string('origen', 30)->default('manual');
            $table->unsignedInteger('origen_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('fecha', 'idx_fecha_egr');
            $table->index('tipo', 'idx_tipo_egr');
            $table->index('venta_id', 'idx_venta_egr');
            $table->index(['origen', 'origen_id'], 'idx_origen_egr');
        });

        Schema::create('metas_mensuales', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('anio');
            $table->integer('mes');
            $table->decimal('meta_galones', 10, 2)->default(0);
            $table->decimal('meta_monto', 12, 2)->default(0);
            $table->timestamp('creado_en')->useCurrent();

            $table->unique(['anio', 'mes'], 'uk_mes');
        });

        Schema::create('costos_mensuales', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('anio');
            $table->integer('mes');
            $table->decimal('costo_productos', 12, 2)->default(0);
            $table->decimal('gastos_operativos', 12, 2)->default(0);
            $table->timestamp('creado_en')->useCurrent();

            $table->unique(['anio', 'mes'], 'uk_cm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_mensuales');
        Schema::dropIfExists('metas_mensuales');
        Schema::dropIfExists('egresos');
        Schema::dropIfExists('ingresos');
        Schema::dropIfExists('cobranzas');
    }
};
