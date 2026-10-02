<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sin esto, nada impedía que una carga de lista futura volviera a crear un
 * código repetido (la causa real detrás de la migración anterior,
 * `consolidar_productos_duplicados`) — un código vacío/nulo sigue
 * permitido (MySQL no choca varios NULL contra un único), solo se exige
 * que un código ya escrito no se repita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unique('codigo', 'productos_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique('productos_codigo_unique');
        });
    }
};
