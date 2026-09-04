<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notificacion_lecturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            // Clave estable del hecho notificado, p.ej. "inventario:123" — no
            // hay una tabla de eventos: el estado "leído" se guarda aparte
            // porque las notificaciones se recalculan en vivo cada carga.
            $table->string('clave');
            $table->timestamp('leido_en')->useCurrent();
            $table->unique(['usuario_id', 'clave']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacion_lecturas');
    }
};
