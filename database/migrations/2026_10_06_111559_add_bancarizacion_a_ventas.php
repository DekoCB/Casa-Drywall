<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del medio de pago bancario que exige la Ley N° 28194
 * (Bancarización) para Facturas mayores a S/ 2,000 / US$ 500 — sin esto,
 * API-GO rechaza el comprobante al registrarlo ante SUNAT (confirmado en
 * producción: una Factura de S/ 3,263.88 quedó con un número nunca
 * confirmado porque ningún campo del formulario lo pedía todavía).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('bancarizacion_medio_pago', 10)->nullable()->after('cliente_departamento');
            $table->string('bancarizacion_numero_operacion', 100)->nullable()->after('bancarizacion_medio_pago');
            $table->date('bancarizacion_fecha_pago')->nullable()->after('bancarizacion_numero_operacion');
            $table->string('bancarizacion_banco', 100)->nullable()->after('bancarizacion_fecha_pago');
            $table->string('bancarizacion_observaciones', 500)->nullable()->after('bancarizacion_banco');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'bancarizacion_medio_pago',
                'bancarizacion_numero_operacion',
                'bancarizacion_fecha_pago',
                'bancarizacion_banco',
                'bancarizacion_observaciones',
            ]);
        });
    }
};
