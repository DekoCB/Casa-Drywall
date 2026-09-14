<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos de "Finanzas y Pagos": bancos, monedas, tarjetas, plataformas
 * de venta/cobro y métodos de pago. Hoy varios de estos viven como texto
 * libre sueltos (`ingresos.metodo_pago`, los botones fijos del POS,
 * `cuentas_bancarias.banco`) — esto no reemplaza esos campos, solo les da
 * un catálogo de referencia administrable desde Configuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('monedas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->string('codigo', 10);
            $table->string('simbolo', 10);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tarjetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('plataformas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $ahora = now();

        DB::table('bancos')->insert(collect(['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Banco de la Nación'])
            ->map(fn ($nombre) => ['nombre' => $nombre, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora])->all());

        DB::table('monedas')->insert([
            ['nombre' => 'Soles', 'codigo' => 'PEN', 'simbolo' => 'S/', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Dólares', 'codigo' => 'USD', 'simbolo' => 'US$', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        DB::table('tarjetas')->insert(collect(['Visa', 'Mastercard', 'American Express', 'Diners Club'])
            ->map(fn ($nombre) => ['nombre' => $nombre, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora])->all());

        DB::table('plataformas')->insert(collect(['Tienda física', 'Página web', 'Yape Negocio', 'Plin Negocio'])
            ->map(fn ($nombre) => ['nombre' => $nombre, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora])->all());

        DB::table('metodos_pago')->insert(collect(['Efectivo', 'Transferencia bancaria', 'Tarjeta', 'Yape', 'Plin', 'Depósito bancario'])
            ->map(fn ($nombre) => ['nombre' => $nombre, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
        Schema::dropIfExists('plataformas');
        Schema::dropIfExists('tarjetas');
        Schema::dropIfExists('monedas');
        Schema::dropIfExists('bancos');
    }
};
