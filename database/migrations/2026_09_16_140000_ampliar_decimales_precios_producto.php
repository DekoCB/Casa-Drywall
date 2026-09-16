<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El negocio necesita costear por unidad productos comprados al por mayor
 * (ej. el precio de un tornillo dentro de una caja de 1000), algo que 2
 * decimales no alcanza a representar sin perder precisión. Ensanchar la
 * escala no pierde ningún dato existente (2 decimales caben dentro de 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_compra', 12, 4)->default(0)->change();
            $table->decimal('precio_venta', 12, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_compra', 10, 2)->default(0)->change();
            $table->decimal('precio_venta', 10, 2)->default(0)->change();
        });
    }
};
