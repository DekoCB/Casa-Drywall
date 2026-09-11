<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->after('fecha');
            $table->string('referencia_venta', 100)->nullable()->after('cliente_ref');
            $table->string('aprobado_por', 100)->nullable()->after('vendedor');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['fecha_vencimiento', 'referencia_venta', 'aprobado_por']);
        });
    }
};
