<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas pasa a ser el registro único del comprobante emitido: además del lado
 * fiscal (base, IGV) guarda el lado de cobranza (vencimiento, pagado, saldo).
 * Antes ese dato vivía suelto en `cobranzas` y la misma factura quedaba
 * anotada en dos sitios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $tabla) {
            $tabla->date('fecha_vencimiento')->nullable()->after('fecha');
            $tabla->decimal('monto_pagado', 12, 2)->default(0)->after('total');
            $tabla->decimal('monto_pendiente', 12, 2)->default(0)->after('monto_pagado');
            $tabla->string('estado_cobro', 20)->default('pendiente')->after('monto_pendiente');
            $tabla->date('fecha_pago')->nullable()->after('estado_cobro');
            $tabla->text('notas_cobranza')->nullable()->after('fecha_pago');

            // De qué fila de `cobranzas` salió, para no volver a importarla.
            $tabla->unsignedInteger('cobranza_id')->nullable()->after('notas_cobranza');

            $tabla->index('estado_cobro');
            $tabla->index('cobranza_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $tabla) {
            $tabla->dropIndex(['estado_cobro']);
            $tabla->dropIndex(['cobranza_id']);
            $tabla->dropColumn([
                'fecha_vencimiento', 'monto_pagado', 'monto_pendiente',
                'estado_cobro', 'fecha_pago', 'notas_cobranza', 'cobranza_id',
            ]);
        });
    }
};
