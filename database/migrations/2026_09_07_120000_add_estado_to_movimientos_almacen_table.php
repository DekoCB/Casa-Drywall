<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_almacen', function (Blueprint $table) {
            // 'entregado' como default a nivel de columna: protege los
            // movimientos históricos (y cualquier otro insert que no lo
            // fije a mano) dejándolos como definitivos, no editables. Los
            // controladores fijan 'en_curso' explícitamente al crear un
            // movimiento nuevo.
            $table->string('estado', 20)->default('entregado')->after('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
