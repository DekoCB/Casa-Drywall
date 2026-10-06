<?php

namespace App\Services;

use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Código QR de Boleta/Factura, formato oficial SUNAT (RS 097-2012/SUNAT):
 * RUC|TipoDoc|Serie|Numero|MontoIGV|MontoTotal|FechaEmision|TipoDocCliente|
 * NumDocCliente| — mismo formato que ya usa API-GO para el PDF oficial
 * (ver `generateQRData()` en ese proyecto), para que el QR sea consistente
 * sin importar qué documento lo imprima.
 *
 * Se genera con un servicio público sin API key (mismo criterio que el mapa
 * de Dirección: cero cuenta/configuración que mantener) y se cachea en disco
 * por venta — así no se vuelve a pedir en cada vista, y dompdf (que no puede
 * pedir imágenes por HTTP) lo lee directo del archivo en vez de la URL.
 */
class CodigoQrService
{
    private const TIPOS_CON_QR = ['01', '03'];

    public function aplica(Venta $venta): bool
    {
        return in_array($venta->tipcomp, self::TIPOS_CON_QR, true);
    }

    /** Ruta real en disco del QR (generándolo y cacheándolo si hace falta), o null si no se pudo. */
    public function rutaEnDisco(Venta $venta): ?string
    {
        if (! $this->aplica($venta)) {
            return null;
        }

        $relativo = "qr/venta-{$venta->id}.png";

        if (Storage::disk('public')->exists($relativo)) {
            return Storage::disk('public')->path($relativo);
        }

        try {
            $respuesta = Http::timeout(5)->get('https://api.qrserver.com/v1/create-qr-code/', [
                'size' => '200x200',
                'data' => $this->datos($venta),
            ]);

            if ($respuesta->failed()) {
                Log::warning('No se pudo generar el código QR del comprobante', ['venta_id' => $venta->id, 'status' => $respuesta->status()]);

                return null;
            }

            Storage::disk('public')->put($relativo, $respuesta->body());

            return Storage::disk('public')->path($relativo);
        } catch (\Throwable $e) {
            Log::warning('Código QR del comprobante lanzó excepción', ['venta_id' => $venta->id, 'mensaje' => $e->getMessage()]);

            return null;
        }
    }

    private function datos(Venta $venta): string
    {
        $ruc = config('rentaltech.empresa.ruc', '');
        $numero = $venta->cliente_ruc ?: $venta->n_ruc ?: '';
        $numeroLimpio = preg_replace('/\D/', '', (string) $numero);

        $tipoDocCliente = match (strlen($numeroLimpio)) {
            11 => '6', // RUC
            8 => '1',  // DNI
            default => '0', // sin documento (p.ej. "Cliente Varios")
        };

        return implode('|', [
            $ruc,
            $venta->tipcomp,
            $venta->n_seri,
            $venta->n_comp,
            number_format((float) $venta->igv, 2, '.', ''),
            number_format((float) $venta->total, 2, '.', ''),
            $venta->fecha?->format('Y-m-d'),
            $tipoDocCliente,
            $tipoDocCliente === '0' ? '' : $numeroLimpio,
        ]).'|';
    }
}
