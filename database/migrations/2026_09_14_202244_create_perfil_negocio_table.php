<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única (id=1) con el nombre comercial, título web y los logos que
 * el negocio sube desde "Mi Empresa" — ver App\Models\PerfilNegocio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfil_negocio', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 150)->nullable();
            $table->string('nombre_comercial', 150)->nullable();
            $table->string('titulo_web', 60)->nullable();
            $table->string('logo_claro')->nullable();
            $table->string('logo_oscuro')->nullable();
            $table->string('favicon')->nullable();
            $table->string('logo_app')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_negocio');
    }
};
