<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Antes vivían fijas en config/rentaltech.php ('cuentas_bancarias'). Se
 * siembran acá las mismas 2 cuentas reales de Casa Drywall para no perder
 * ese dato al pasar a base de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('banco', 60);
            $table->string('abrev', 10);
            $table->string('moneda', 30)->default('S/ Soles');
            $table->string('titular', 150);
            $table->string('cuenta', 60);
            $table->string('cci', 60);
            $table->string('color', 9)->default('#0d47a1');
            $table->string('bg', 9)->default('#e8f0fe');
            $table->timestamps();
        });

        DB::table('cuentas_bancarias')->insert([
            [
                'banco' => 'BBVA', 'abrev' => 'BBVA', 'moneda' => 'S/ Soles',
                'titular' => 'BBVA - CASA DRYWALL E.I.R.L.',
                'cuenta' => '0011-0241-0200859015-76', 'cci' => '011-241-000200859015-76',
                'color' => '#0d47a1', 'bg' => '#e8f0fe',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'banco' => 'INTERBANK', 'abrev' => 'IBK', 'moneda' => 'S/ Soles',
                'titular' => 'INTERBANK - CASA DRYWALL E.I.R.L.',
                'cuenta' => '404-3006076405-14', 'cci' => '003-404-003006076405-14',
                'color' => '#1b5e20', 'bg' => '#f0faf4',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
