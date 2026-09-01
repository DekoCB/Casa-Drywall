<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de galonaje que el dashboard y los reportes de rendimiento
 * consultan sobre ventas y su detalle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('galones_total', 12, 3)->default(0)->after('total');
        });

        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->decimal('galones', 12, 3)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->dropColumn('galones');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('galones_total');
        });
    }
};
