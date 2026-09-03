<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('sesion_caja_id')->nullable()->after('almacen_id');
            $table->string('canal', 20)->default('backoffice')->after('sesion_caja_id'); // backoffice | pos
            $table->decimal('vuelto', 10, 2)->default(0)->after('total');
            $table->decimal('descuento_total', 10, 2)->default(0)->after('vuelto');
            $table->string('pos_token', 40)->nullable()->unique()->after('descuento_total');

            $table->index('sesion_caja_id');
            $table->index('canal');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['sesion_caja_id', 'canal', 'vuelto', 'descuento_total', 'pos_token']);
        });
    }
};
