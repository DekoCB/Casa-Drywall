<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial: cotizaciones, ventas y sus detalles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->increments('id');
            $table->string('numero', 30);
            $table->string('cliente_nombre', 255)->nullable();
            $table->string('cliente_ruc', 20)->nullable();
            $table->string('cliente_telefono', 50)->nullable();
            $table->string('cliente_correo', 150)->nullable();
            $table->text('cliente_direccion')->nullable();
            $table->string('cliente_distrito', 100)->nullable();
            $table->string('condicion_pago', 100)->nullable();
            $table->string('tipo_envio', 50)->nullable();
            $table->decimal('costo_transporte', 10, 2)->default(0);
            $table->string('destino_entrega', 150)->nullable();
            $table->string('empresa_transporte', 200)->nullable();
            $table->string('vendedor', 150)->nullable();
            $table->string('codigo_vendedor', 50)->nullable();
            $table->integer('vigencia_dias')->default(7);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('con_igv')->default(true);
            $table->text('observaciones')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->unsignedInteger('venta_id')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->date('fecha');
            $table->timestamp('created_at')->useCurrent();

            $table->index('numero');
            $table->index('fecha');
        });

        Schema::create('cotizacion_detalle', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotizacion_id');
            $table->string('producto_codigo', 50)->nullable();
            $table->string('producto_nombre', 255)->nullable();
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2)->default(0);
            $table->decimal('subtotal_item', 12, 2)->default(0);

            $table->index('cotizacion_id');
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('numero_venta', 50)->nullable();
            $table->string('tipo_comprobante', 50)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('metodo_pago', 50)->nullable();
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('estado', 30)->default('completada');

            // Cliente (denormalizado, como en el original)
            $table->string('cliente_nombre', 255)->nullable();
            $table->string('cliente_ruc', 20)->nullable();
            $table->string('cliente_telefono', 50)->nullable();
            $table->string('cliente_correo', 150)->nullable();
            $table->text('cliente_direccion')->nullable();
            $table->string('cliente_distrito', 100)->nullable();

            // Comercial / logística
            $table->string('condicion_pago', 100)->nullable();
            $table->string('empresa_transporte', 200)->nullable();
            $table->string('vendedor', 150)->nullable();
            $table->string('codigo_vendedor', 50)->nullable();
            $table->string('destino_entrega', 150)->nullable();
            $table->string('tipo_envio', 50)->nullable();
            $table->decimal('costo_transporte', 10, 2)->default(0);
            $table->decimal('gasto_gasolina', 10, 2)->default(0);

            // Moneda / regalo
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 10, 4)->nullable();
            $table->boolean('tiene_regalo')->default(false);
            $table->string('regalo_descripcion', 255)->nullable();
            $table->decimal('regalo_precio', 10, 2)->nullable();

            // Campos SUNAT
            $table->string('tipcomp', 5)->default('01');
            $table->string('n_seri', 10)->default('');
            $table->string('n_comp', 20)->default('');
            $table->string('n_ruc', 20)->default('');
            $table->string('razonsocial', 300)->default('');
            $table->decimal('baseimp', 14, 2)->default(0);
            $table->decimal('exonerado', 14, 2)->default(0);
            $table->decimal('inafecto', 14, 2)->default(0);
            $table->decimal('tipcambio', 8, 4)->default(1);

            $table->timestamp('created_at')->useCurrent();

            $table->index('fecha');
            $table->index('numero_venta');
            $table->index('estado');
        });

        Schema::create('venta_detalle', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('venta_id');
            $table->unsignedInteger('producto_id')->nullable();
            $table->string('prod_codigo', 50)->nullable();
            $table->string('prod_nombre', 255)->nullable();
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->index('venta_id');
        });

        Schema::create('pedidos_clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fecha');
            $table->string('cliente_nombre', 200);
            $table->string('ruc', 20)->default('');
            $table->string('telefono', 50)->default('');
            $table->string('destino', 150)->default('');
            $table->string('empresa_transporte', 200)->default('');
            $table->text('productos')->nullable();
            $table->decimal('total_soles', 12, 2)->default(0);
            $table->string('estado', 50)->default('Pendiente');
            $table->text('observaciones')->nullable();
            $table->string('archivo_pedido', 300)->default('');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_clientes');
        Schema::dropIfExists('venta_detalle');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('cotizacion_detalle');
        Schema::dropIfExists('cotizaciones');
    }
};
