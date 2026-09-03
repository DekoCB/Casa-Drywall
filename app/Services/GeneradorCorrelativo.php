<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Genera los números de documento con el mismo formato del proyecto original
 * (`PREFIJO-AAAAMMDD-NNNN`).
 *
 * El original tomaba los 4 dígitos con `rand()`, lo que podía colisionar. Aquí
 * el sufijo es el correlativo del día, así que el formato es idéntico pero el
 * número no se repite.
 */
class GeneradorCorrelativo
{
    public function siguiente(string $tabla, string $columna, string $prefijo): string
    {
        $fecha = now()->format('Ymd');
        $base = "{$prefijo}-{$fecha}-";

        $ultimo = DB::table($tabla)
            ->where($columna, 'like', $base.'%')
            ->orderByDesc($columna)
            ->value($columna);

        $secuencia = $ultimo
            ? ((int) substr($ultimo, strlen($base))) + 1
            : 1;

        return $base.str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT);
    }

    public function venta(): string
    {
        return $this->siguiente('ventas', 'numero_venta', 'V');
    }

    /**
     * Las órdenes de compra no llevan prefijo: el original guarda el último
     * número usado en `config_sistema.correlativo_oc` y entrega el siguiente
     * con seis dígitos (000425). Sólo se consume al guardar la orden.
     */
    public function ordenCompra(): string
    {
        $ultimo = (int) (DB::table('config_sistema')->where('clave', 'correlativo_oc')->value('valor') ?? 366);

        return str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT);
    }

    /** Marca el número como usado para que la siguiente orden no lo repita. */
    public function consumirOrdenCompra(string $numero): void
    {
        $valor = (int) ltrim($numero, '0');

        if ($valor <= 0) {
            return;
        }

        $actual = (int) (DB::table('config_sistema')->where('clave', 'correlativo_oc')->value('valor') ?? 0);

        // Si el usuario escribió un número menor al último, no se retrocede.
        DB::table('config_sistema')->updateOrInsert(
            ['clave' => 'correlativo_oc'],
            ['valor' => (string) max($actual, $valor)]
        );
    }

    public function guiaRemision(): string
    {
        return $this->siguiente('guias_remision', 'numero_guia', 'GR');
    }

    /**
     * Correlativo de los documentos internos (Cotización, Nota de Venta):
     * a diferencia de Factura/Boleta, aquí no hay un tercero (SUNAT/API-GO)
     * que asigne el número, así que se calcula a partir del último usado
     * para el mismo tipo y serie. Reemplaza siempre lo que haya escrito el
     * usuario, para que nunca deje de ser correlativo.
     */
    public function documentoInterno(string $tipcomp, string $serie): string
    {
        $ultimo = (int) DB::table('ventas')
            ->where('tipcomp', $tipcomp)
            ->where('n_seri', $serie)
            ->max(DB::raw('CAST(n_comp AS UNSIGNED)'));

        return str_pad((string) ($ultimo + 1), 8, '0', STR_PAD_LEFT);
    }
}
