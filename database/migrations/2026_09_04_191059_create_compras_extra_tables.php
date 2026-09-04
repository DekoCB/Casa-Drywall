<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tres registros nuevos del módulo Compras: Activos Fijos, Solicitud de
 * Cotización a proveedor, y Liquidación de Compra (registro interno, no
 * es un comprobante SUNAT electrónico — ver LiquidacionCompraController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos_fijos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->nullable();
            $table->string('nombre', 255);
            $table->string('categoria', 50)->nullable();
            $table->unsignedInteger('proveedor_id')->nullable();
            $table->date('fecha_compra');
            $table->decimal('costo', 10, 2)->default(0);
            $table->string('estado', 20)->default('activo');
            $table->string('ubicacion', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('proveedor_id')->references('id')->on('proveedores')->nullOnDelete();
        });

        Schema::create('cotizaciones_proveedor', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->date('fecha');
            $table->unsignedInteger('proveedor_id');
            $table->json('productos')->nullable();
            $table->string('estado', 20)->default('enviada');
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('proveedor_id')->references('id')->on('proveedores')->cascadeOnDelete();
        });

        Schema::create('liquidaciones_compra', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->date('fecha');
            $table->string('vendedor_nombre', 255);
            $table->string('vendedor_documento', 20)->nullable();
            $table->unsignedInteger('proveedor_id')->nullable();
            $table->json('productos')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('proveedor_id')->references('id')->on('proveedores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_compra');
        Schema::dropIfExists('cotizaciones_proveedor');
        Schema::dropIfExists('activos_fijos');
    }
};
