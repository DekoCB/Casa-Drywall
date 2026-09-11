<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un producto tiene, como mucho, un proveedor principal (el de la última carga por Excel). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // unsignedInteger, no foreignId: `proveedores.id` es increments() (INT),
            // no bigIncrements() -- foreignId() crea BIGINT y no calza con la FK.
            $table->unsignedInteger('proveedor_id')->nullable()->after('marca_id');
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn('proveedor_id');
        });
    }
};
