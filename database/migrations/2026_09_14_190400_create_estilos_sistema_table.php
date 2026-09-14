<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única (id=1) con el color de acento y la tipografía que elige el
 * negocio para todo el sistema — ver App\Models\EstiloSistema::actual().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estilos_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('color', 20)->default('amarillo');
            $table->string('fuente', 20)->default('casa-drywall');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estilos_sistema');
    }
};
