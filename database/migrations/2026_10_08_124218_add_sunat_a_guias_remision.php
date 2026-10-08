<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta ahora "Guía de Remisión" era un módulo 100% local — se guardaba
 * con numeración propia (GR-AAAAMMDD-NNNN) y nunca se avisaba a SUNAT, así
 * que no era un comprobante válido de verdad, solo un papel interno. Estas
 * columnas son las que hacen falta para registrarla en el sistema de
 * facturación electrónica (API-GO), mismo patrón que ya tiene `ventas`
 * para Boleta/Factura (estado_factura/numero_sunat/nota_contadora/
 * api_go_document_id) — nombradas aparte acá para no confundir con las de
 * Venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            // Catálogo 20 de SUNAT (motivo de traslado) y modalidad — hoy
            // `motivo_traslado` es texto libre elegido a mano, esto es el
            // código real que SUNAT exige.
            $table->string('cod_traslado', 2)->nullable()->after('motivo_traslado');
            $table->string('mod_traslado', 2)->nullable()->after('cod_traslado');

            // Ubigeo (6 dígitos) de origen/destino — SUNAT lo exige, hoy
            // solo se guarda la dirección como texto libre.
            $table->string('partida_ubigeo', 6)->nullable()->after('punto_partida');
            $table->string('llegada_ubigeo', 6)->nullable()->after('punto_llegada');

            $table->string('und_peso_total', 3)->default('KGM')->after('peso_total');

            // SUNAT exige el documento del conductor (no solo su licencia)
            // para transporte privado — el formulario original nunca lo
            // pedía porque nunca se registraba nada ante SUNAT.
            $table->string('conductor_dni', 15)->nullable()->after('conductor_nombre');

            // Seguimiento ante SUNAT — las Guías de Remisión se procesan
            // async en SUNAT (a diferencia de Boleta/Factura), de ahí el
            // ticket a consultar después.
            $table->string('estado_sunat', 20)->nullable()->after('estado');
            $table->string('numero_sunat', 30)->nullable()->after('estado_sunat');
            $table->text('nota_sunat')->nullable()->after('numero_sunat');
            $table->unsignedInteger('api_go_document_id')->nullable()->after('nota_sunat');
            $table->string('api_go_ticket', 50)->nullable()->after('api_go_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            $table->dropColumn([
                'cod_traslado', 'mod_traslado', 'partida_ubigeo', 'llegada_ubigeo',
                'und_peso_total', 'conductor_dni', 'estado_sunat', 'numero_sunat', 'nota_sunat',
                'api_go_document_id', 'api_go_ticket',
            ]);
        });
    }
};
