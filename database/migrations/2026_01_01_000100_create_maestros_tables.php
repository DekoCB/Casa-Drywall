<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestros: clientes, proveedores, personal, categorias, marcas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipo_documento', 20)->default('DNI');
            $table->string('numero_documento', 20);
            $table->string('nombres', 255);
            $table->string('nombre_empresa', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->date('fecha_cumpleanos')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();

            $table->index('numero_documento');
            $table->index('nombres');
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ruc', 20);
            $table->string('razon_social', 255);
            $table->string('contacto', 150)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->date('fecha_cumpleanos')->nullable();
            $table->text('productos_suministra')->nullable();
            $table->string('condiciones_pago', 100)->nullable();
            $table->integer('dias_credito')->default(0);
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();

            $table->index('ruc');
        });

        Schema::create('personal', function (Blueprint $table) {
            $table->increments('id');
            $table->string('dni', 15);
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('cargo', 100);
            $table->string('area', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->decimal('sueldo', 10, 2)->default(0);
            $table->string('tipo_contrato', 50)->default('Planilla');
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('marcas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('empresas_transporte', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 200);
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('tarifas_transporte', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id')->nullable();
            $table->string('destino', 100);
            $table->decimal('precio_baldes', 10, 2)->default(0);
            $table->decimal('precio_cajas', 10, 2)->default(0);
            $table->decimal('precio_cilindros', 10, 2)->default(0);
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('email_contactos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 120);
            $table->string('correo', 180)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('config_sistema', function (Blueprint $table) {
            $table->string('clave', 50)->primary();
            $table->string('valor', 255)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_sistema');
        Schema::dropIfExists('email_contactos');
        Schema::dropIfExists('tarifas_transporte');
        Schema::dropIfExists('empresas_transporte');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('personal');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('clientes');
    }
};
