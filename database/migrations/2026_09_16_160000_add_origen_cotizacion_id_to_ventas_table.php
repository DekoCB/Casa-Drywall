<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `venta_origen_id` ya existe, pero es exclusivo de Nota de Crédito/Débito
 * (`comprobante.blade.php` lo usa para armar "Corresponde a: ... — {motivo}",
 * leyendo `cod_motivo` con los catálogos SUNAT de crédito/débito) — reusarlo
 * para "esta venta salió de esta Cotización" rompería esa pantalla. Columna
 * propia, misma idea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('origen_cotizacion_id')->nullable()->after('venta_origen_id');
        });

        // Hasta ahora el único rastro de qué Cotización generó una venta real
        // vivía como texto libre en `observaciones`
        // ("Generado desde Cotización CT01-00001") — se recupera acá para
        // que el historial ya cargado en producción también quede enlazado.
        DB::table('ventas')
            ->where('tipcomp', '!=', 'COT')
            ->where('observaciones', 'like', 'Generado desde Cotización %')
            ->orderBy('id')
            ->chunkById(200, function ($ventas) {
                foreach ($ventas as $venta) {
                    $texto = Str::after($venta->observaciones, 'Generado desde Cotización ');

                    if (! Str::contains($texto, '-')) {
                        continue;
                    }

                    [$seri, $comp] = explode('-', $texto, 2);

                    $cotizacion = DB::table('ventas')
                        ->where('tipcomp', 'COT')
                        ->where('n_seri', $seri)
                        ->where('n_comp', $comp)
                        ->first(['id']);

                    if ($cotizacion) {
                        DB::table('ventas')->where('id', $venta->id)->update(['origen_cotizacion_id' => $cotizacion->id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('origen_cotizacion_id');
        });
    }
};
