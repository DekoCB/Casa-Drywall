<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borra el modulo "Facturas" (facturas pendientes por pagar a GP Maquinarias
 * SAC, ligado al catalogo Kendall/P66 que ya no se usa) junto con sus datos,
 * a pedido explicito del cliente. Ver 2026_08_07_213124_crear_tabla_facturas.php
 * para la estructura original si hiciera falta reconstruirla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('facturas');
    }

    public function down(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->string('doc', 40)->nullable();
            $table->string('guia_remision', 60)->nullable();
            $table->date('emision');
            $table->date('vencimiento');
            $table->decimal('importe', 12, 2)->default(0);
            $table->decimal('tc', 8, 2)->default(0);
            $table->decimal('galones', 12, 2)->default(0);
            $table->string('producto', 255)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->boolean('cancelado')->default(false);
            $table->string('estado_manual', 20)->nullable();
            $table->json('productos_lista')->nullable();
            $table->string('pdf', 255)->nullable();
            $table->timestamps();
            $table->index('emision');
            $table->index('vencimiento');
        });
    }
};
