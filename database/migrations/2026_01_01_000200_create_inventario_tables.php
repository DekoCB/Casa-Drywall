<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario: productos, almacenes, stock por almacén, movimientos y merch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 50)->nullable();
            $table->string('nombre', 255);
            $table->unsignedInteger('categoria_id')->nullable();
            $table->unsignedInteger('marca_id')->nullable();
            $table->string('presentacion', 100)->nullable();
            $table->string('viscosidad', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('especificaciones')->nullable();
            $table->decimal('precio_compra', 10, 2)->default(0);
            $table->decimal('precio_venta', 10, 2)->default(0);
            $table->decimal('precio_alquiler', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->decimal('peso', 10, 3)->nullable();
            $table->string('imagen', 255)->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();

            $table->index('codigo');
            $table->index('estado');
        });

        Schema::create('almacenes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('stock_almacen', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('producto_id');
            $table->unsignedInteger('almacen_id');
            $table->integer('stock')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['producto_id', 'almacen_id'], 'uq_producto_almacen');
            $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
            $table->foreign('almacen_id')->references('id')->on('almacenes')->cascadeOnDelete();
        });

        Schema::create('movimientos_almacen', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('producto_id');
            $table->unsignedInteger('almacen_id')->default(1);
            $table->string('tipo', 20);            // entrada | salida | ajuste | traslado
            $table->integer('cantidad')->default(0);
            $table->integer('stock_anterior')->nullable();
            $table->integer('stock_nuevo')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('producto_id');
            $table->index('almacen_id');
        });

        Schema::create('merch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 150);
            $table->string('categoria', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->timestamp('creado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch');
        Schema::dropIfExists('movimientos_almacen');
        Schema::dropIfExists('stock_almacen');
        Schema::dropIfExists('almacenes');
        Schema::dropIfExists('productos');
    }
};
