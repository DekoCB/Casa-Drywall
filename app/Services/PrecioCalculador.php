<?php

namespace App\Services;

/**
 * Cálculo de precios/IGV compartido entre el formulario de Ventas y el
 * Punto de Venta — una sola fuente para no duplicar la lógica de IGV en
 * dos sitios.
 */
class PrecioCalculador
{
    /**
     * Separa un monto gravado en base imponible + IGV. Si el precio ya trae
     * el IGV incluido (precio de venta al público, lo normal en el catálogo
     * de productos), se extrae en vez de sumarse encima — de lo contrario se
     * estaría cobrando el IGV dos veces.
     */
    public function desglosarImporte(float $monto, bool $incluyeIgv): array
    {
        if ($incluyeIgv) {
            $base = round($monto / (1 + config('rentaltech.igv')), 2);

            return ['base' => $base, 'igv' => round($monto - $base, 2)];
        }

        return ['base' => round($monto, 2), 'igv' => round($monto * config('rentaltech.igv'), 2)];
    }

    /**
     * Total de una línea de venta con descuento por ítem, a partir de un
     * precio que ya incluye IGV (el precio de venta del catálogo).
     */
    public function calcularLinea(float $cantidad, float $precioUnitario, float $descuentoPct = 0): array
    {
        $bruto = round($cantidad * $precioUnitario, 2);
        $descuento = round($bruto * ($descuentoPct / 100), 2);

        return ['bruto' => $bruto, 'descuento' => $descuento, 'neto' => round($bruto - $descuento, 2)];
    }
}
