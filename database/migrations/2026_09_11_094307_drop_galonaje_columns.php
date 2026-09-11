<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reversa el módulo de Galonaje: quita las columnas que agregó (ver 2026_01_01_000600_add_galonaje_columns.php). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('galones_total');
        });

        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->dropColumn('galones');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('galones_total', 12, 3)->default(0)->after('total');
        });

        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->decimal('galones', 12, 3)->default(0)->after('subtotal');
        });
    }
};
