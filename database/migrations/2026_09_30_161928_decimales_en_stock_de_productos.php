<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El negocio ya tiene stock real que no es un número entero (confirmado por
 * la lista oficial de productos importada el 16 sep: códigos como el 00024
 * traían un stock de "33.75" que esa migración tuvo que ignorar a propósito
 * porque la columna era entera). Se ensancha `stock`/`stock_minimo` de
 * Producto, `stock_almacen.stock` y `movimientos_almacen.cantidad` +
 * `stock_anterior` + `stock_nuevo` a decimal(12,3) — 3 decimales alcanza
 * para kg/m/L fraccionados sin acumular error de redondeo. Ensanchar la
 * escala no pierde ningún dato existente (un entero cabe dentro de 3
 * decimales), mismo criterio que ya se usó para precios el 16 sep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock', 12, 3)->default(0)->change();
            $table->decimal('stock_minimo', 12, 3)->default(0)->change();
        });

        Schema::table('stock_almacen', function (Blueprint $table) {
            $table->decimal('stock', 12, 3)->default(0)->change();
        });

        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 3)->default(0)->change();
            $table->decimal('stock_anterior', 12, 3)->nullable()->change();
            $table->decimal('stock_nuevo', 12, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->integer('stock')->default(0)->change();
            $table->integer('stock_minimo')->default(0)->change();
        });

        Schema::table('stock_almacen', function (Blueprint $table) {
            $table->integer('stock')->default(0)->change();
        });

        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->integer('cantidad')->default(0)->change();
            $table->integer('stock_anterior')->nullable()->change();
            $table->integer('stock_nuevo')->nullable()->change();
        });
    }
};
