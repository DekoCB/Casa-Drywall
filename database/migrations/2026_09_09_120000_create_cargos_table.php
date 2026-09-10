<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de Cargos para el formulario de Personal — antes era un
     * texto libre (`personal.cargo`), que se deja tal cual para no romper
     * los registros existentes; esta tabla solo alimenta el desplegable.
     */
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 100)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        // Los cargos que ya estén en uso quedan disponibles en el catálogo
        // desde el primer momento, para que ningún colaborador existente
        // quede con un valor que no aparece en la lista al editarlo.
        DB::table('personal')
            ->whereNotNull('cargo')
            ->where('cargo', '!=', '')
            ->distinct()
            ->pluck('cargo')
            ->each(function (string $nombre) {
                DB::table('cargos')->insertOrIgnore([
                    'nombre' => trim($nombre),
                    'created_at' => now(),
                ]);
            });

        DB::table('cargos')->insertOrIgnore([
            'nombre' => 'Ventas',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
