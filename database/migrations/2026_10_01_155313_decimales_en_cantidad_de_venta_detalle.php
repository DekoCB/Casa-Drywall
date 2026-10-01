<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El negocio pidió vender fracciones de un producto (media, un cuarto, un
 * tercio) en Cotización, Nota de Venta, Boleta y Factura — `venta_detalle`
 * ya pasaba la cantidad entera por `StockAlmacen`/`MovimientoAlmacen`, que
 * desde la migración de inventario ya son decimal(12,3); esta completa esa
 * misma precisión del lado de la venta. Ensanchar la escala no pierde
 * ningún dato existente (un entero cabe dentro de 3 decimales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 3)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->integer('cantidad')->default(1)->change();
        });
    }
};
