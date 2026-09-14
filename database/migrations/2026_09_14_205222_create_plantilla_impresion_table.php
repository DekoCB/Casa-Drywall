<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única (id=1) con la plantilla activa del comprobante impreso en
 * PDF (A4) y del ticket (80mm) — ver App\Models\PlantillaImpresion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_impresion', function (Blueprint $table) {
            $table->id();
            $table->string('pdf', 20)->default('pro');
            $table->string('ticket', 20)->default('pro');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_impresion');
    }
};
