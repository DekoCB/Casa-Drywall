<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas presentes en la base de producción (`u188616411_Rental12`) que no
 * aparecían en el código PHP del que se reconstruyó el esquema. Se añaden para
 * poder migrar los datos históricos sin pérdidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->after('created_at');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->after('created_at');
        });

        Schema::table('cobranzas', function (Blueprint $table) {
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->after('created_at');
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('descripcion');
        });

        Schema::table('marcas', function (Blueprint $table) {
            $table->string('logo', 255)->nullable()->after('descripcion');
            $table->string('estado', 20)->default('activo')->after('logo');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->string('sku', 50)->nullable()->after('codigo');
            $table->unsignedInteger('subcategoria_id')->nullable()->after('categoria_id');
            $table->string('ficha_tecnica_url', 255)->nullable()->after('imagen');
            $table->string('imagen_principal', 255)->nullable()->after('ficha_tecnica_url');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->after('created_at');
        });

        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->date('fecha')->nullable()->after('tipo');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('cliente_id')->nullable()->after('cliente_nombre');
            $table->string('numero_comprobante', 50)->nullable()->after('tipo_comprobante');
            $table->string('cliente_provincia', 100)->nullable()->after('cliente_distrito');
            $table->string('cliente_departamento', 100)->nullable()->after('cliente_provincia');

            // Seguimiento del comprobante ante SUNAT.
            $table->string('estado_factura', 30)->default('pendiente')->after('estado');
            $table->text('nota_contadora')->nullable()->after('estado_factura');
            $table->date('fecha_emision_sunat')->nullable()->after('nota_contadora');
            $table->string('numero_sunat', 50)->nullable()->after('fecha_emision_sunat');
            $table->string('numero_nota_credito', 50)->nullable()->after('numero_sunat');
            $table->string('numero_nota_debito', 50)->nullable()->after('numero_nota_credito');
        });

        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->string('moneda', 3)->default('PEN')->after('subtotal');
            $table->timestamp('created_at')->useCurrent()->after('galones');
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->unsignedInteger('cliente_id')->nullable()->after('tipo');
            $table->string('numero_comprobante', 50)->nullable()->after('cliente_id');
            $table->text('observaciones')->nullable()->after('metodo_pago');
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->string('proveedor', 200)->nullable()->after('descripcion');
            $table->string('metodo_pago', 30)->nullable()->default('efectivo')->after('proveedor');
            $table->string('numero_comprobante', 50)->nullable()->after('metodo_pago');
            $table->text('observaciones')->nullable()->after('numero_comprobante');
        });
    }

    public function down(): void
    {
        Schema::table('egresos', fn (Blueprint $t) => $t->dropColumn(['proveedor', 'metodo_pago', 'numero_comprobante', 'observaciones']));
        Schema::table('ingresos', fn (Blueprint $t) => $t->dropColumn(['cliente_id', 'numero_comprobante', 'observaciones']));
        Schema::table('venta_detalle', fn (Blueprint $t) => $t->dropColumn(['moneda', 'created_at']));
        Schema::table('ventas', fn (Blueprint $t) => $t->dropColumn([
            'cliente_id', 'numero_comprobante', 'cliente_provincia', 'cliente_departamento',
            'estado_factura', 'nota_contadora', 'fecha_emision_sunat', 'numero_sunat',
            'numero_nota_credito', 'numero_nota_debito',
        ]));
        Schema::table('movimientos_almacen', fn (Blueprint $t) => $t->dropColumn('fecha'));
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['sku', 'subcategoria_id', 'ficha_tecnica_url', 'imagen_principal', 'updated_at']));
        Schema::table('marcas', fn (Blueprint $t) => $t->dropColumn(['logo', 'estado']));
        Schema::table('categorias', fn (Blueprint $t) => $t->dropColumn('orden'));
        Schema::table('cobranzas', fn (Blueprint $t) => $t->dropColumn('updated_at'));
        Schema::table('proveedores', fn (Blueprint $t) => $t->dropColumn('updated_at'));
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn('updated_at'));
    }
};
