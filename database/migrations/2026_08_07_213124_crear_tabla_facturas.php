<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas pendientes por pagar a GP Maquinarias SAC.
 *
 * En el original vivían en `facturas_extra.json`, con las canceladas en un
 * segundo archivo y el detalle de productos en un tercero. Aquí es una sola
 * tabla: el detalle va en una columna JSON y el estado en sus propias banderas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('numero', 40)->unique();          // F005-0009252
            $tabla->string('doc', 40)->default('');          // N° de documento interno
            $tabla->string('guia_remision', 60)->default('');
            $tabla->date('emision');
            $tabla->date('vencimiento');
            $tabla->decimal('importe', 12, 2)->default(0);   // en dólares
            $tabla->decimal('tc', 8, 2)->default(0);         // tipo de cambio aplicado
            $tabla->decimal('galones', 12, 2)->default(0);
            $tabla->string('producto', 255)->default('');
            $tabla->string('cliente', 255)->default('');
            $tabla->boolean('cancelado')->default(false);
            // Vacío = el estado se calcula por la fecha de vencimiento.
            $tabla->string('estado_manual', 20)->default('');
            $tabla->json('productos_lista')->nullable();
            $tabla->string('pdf', 255)->nullable();
            $tabla->timestamps();

            $tabla->index('emision');
            $tabla->index('vencimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
