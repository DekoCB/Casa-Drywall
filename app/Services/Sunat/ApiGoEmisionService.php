<?php

namespace App\Services\Sunat;

use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador hacia API-GO (proyecto Laravel aparte, ver
 * API-GO-Facturacion-Electronica-sunat-peru-main/), que implementa la
 * emisión electrónica real de comprobantes ante SUNAT vía Greenter.
 *
 * Mismo patrón que ConsultaDocumentoService: peticiones con timeout corto,
 * nunca lanza excepciones hacia el llamador (devuelve null/false y registra
 * un warning), para que un fallo o caída de API-GO nunca bloquee una venta.
 */
class ApiGoEmisionService
{
    /** Tipo de documento API-GO (para `api_go_document_type`) según el código SUNAT de la Venta. */
    private const TIPO_DOCUMENTO = [
        '03' => 'boleta',
        '01' => 'invoice',
        '07' => 'credit_note',
        '08' => 'debit_note',
    ];

    /**
     * Registra localmente en API-GO (sin enviar a SUNAT todavía) el
     * comprobante de una Venta recién guardada. Aplica a Boleta (03),
     * Factura (01), Nota de Crédito (07) y Nota de Débito (08).
     */
    public function crearComprobante(Venta $venta): bool
    {
        if (! array_key_exists($venta->tipcomp, self::TIPO_DOCUMENTO)) {
            return false;
        }

        $tipo = self::TIPO_DOCUMENTO[$venta->tipcomp];
        $endpoint = '/'.$this->recursoApiGo($tipo);

        // API-GO deduplica el Client internamente (por numero_documento) al
        // crear el comprobante a partir de los datos embebidos — no hace
        // falta buscar/crear el cliente por separado antes de esta llamada.
        $payload = [
            'company_id' => config('services.api_go.company_id'),
            'branch_id' => config('services.api_go.branch_id'),
            'serie' => $venta->n_seri,
            'fecha_emision' => optional($venta->fecha)->format('Y-m-d') ?: now()->format('Y-m-d'),
            'moneda' => $venta->moneda ?: 'PEN',
            'metodo_envio' => 'individual',
            'client' => $this->datosCliente($venta),
            'detalles' => $this->datosDetalles($venta),
        ];

        if ($tipo === 'invoice') {
            $payload['forma_pago_tipo'] = $this->esCredito($venta) ? 'Credito' : 'Contado';
        }

        if (in_array($tipo, ['credit_note', 'debit_note'], true)) {
            $origen = $venta->ventaOrigen;

            unset($payload['metodo_envio']);
            $payload['tipo_doc_afectado'] = $origen->tipcomp;
            $payload['num_doc_afectado'] = "{$origen->n_seri}-{$origen->n_comp}";
            $payload['cod_motivo'] = $venta->cod_motivo;
            $payload['des_motivo'] = $tipo === 'credit_note'
                ? (Venta::MOTIVOS_CREDITO[$venta->cod_motivo] ?? 'Sin especificar')
                : (Venta::MOTIVOS_DEBITO[$venta->cod_motivo] ?? 'Sin especificar');
        }

        $respuesta = $this->peticion('post', $endpoint, $payload);

        if (! $respuesta || empty($respuesta['success']) || empty($respuesta['data']['id'])) {
            Log::warning('No se pudo registrar el comprobante en API-GO', [
                'venta_id' => $venta->id,
                'respuesta' => $respuesta,
            ]);

            return false;
        }

        $venta->update([
            'api_go_document_id' => $respuesta['data']['id'],
            'api_go_document_type' => $tipo,
            'estado_factura' => 'registrado',
            'numero_sunat' => $respuesta['data']['numero_completo'] ?? null,
        ]);

        return true;
    }

    /**
     * Envía a SUNAT (real) el comprobante ya registrado en API-GO.
     */
    public function enviarSunat(Venta $venta): bool
    {
        if (! $venta->api_go_document_id || ! $venta->api_go_document_type) {
            return false;
        }

        $recurso = $this->recursoApiGo($venta->api_go_document_type);

        $respuesta = $this->peticion('post', "/{$recurso}/{$venta->api_go_document_id}/send-sunat");

        if (! $respuesta) {
            $venta->update([
                'estado_factura' => 'error',
                'nota_contadora' => 'No se pudo contactar al servicio de facturación electrónica.',
            ]);

            return false;
        }

        $exito = (bool) ($respuesta['success'] ?? false);
        $estadoSunat = $respuesta['data']['estado_sunat'] ?? null;

        $venta->update([
            'estado_factura' => $exito && $estadoSunat === 'ACEPTADO' ? 'aceptado' : 'rechazado',
            'nota_contadora' => $respuesta['message'] ?? null,
        ]);

        return $exito;
    }

    /**
     * Devuelve el PDF oficial (bytes) del comprobante. La ruta autenticada
     * de API-GO lo genera sola si todavía no existe — no hace falta un
     * paso previo de "generate-pdf" por separado.
     */
    public function obtenerPdf(Venta $venta): ?string
    {
        if (! $venta->api_go_document_id || ! $venta->api_go_document_type) {
            return null;
        }

        $recurso = $this->recursoApiGo($venta->api_go_document_type);
        $baseUrl = rtrim(config('services.api_go.base_url'), '/');
        $token = config('services.api_go.token');

        try {
            $respuesta = Http::timeout(20)
                ->when($token, fn ($http) => $http->withToken($token))
                ->get("{$baseUrl}/{$recurso}/{$venta->api_go_document_id}/download-pdf");

            if ($respuesta->failed()) {
                Log::warning('Descarga de PDF de API-GO falló', [
                    'venta_id' => $venta->id,
                    'status' => $respuesta->status(),
                ]);

                return null;
            }

            return $respuesta->body();
        } catch (\Throwable $e) {
            Log::warning('Descarga de PDF de API-GO lanzó excepción', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Segmento de ruta en API-GO (`/{recurso}/...`) según `api_go_document_type`. */
    private function recursoApiGo(string $tipo): string
    {
        return match ($tipo) {
            'boleta' => 'boletas',
            'credit_note' => 'credit-notes',
            'debit_note' => 'debit-notes',
            default => 'invoices',
        };
    }

    private function datosCliente(Venta $venta): array
    {
        $numero = $venta->cliente_ruc ?: $venta->n_ruc ?: '00000000';

        return [
            'tipo_documento' => $this->codigoTipoDocumento($numero),
            'numero_documento' => $numero,
            'razon_social' => $venta->razonsocial ?: $venta->cliente_nombre ?: 'CLIENTE VARIOS',
            'direccion' => $venta->cliente_direccion,
            'distrito' => $venta->cliente_distrito,
            'telefono' => $venta->cliente_telefono,
            'email' => $venta->cliente_correo,
        ];
    }

    private function datosDetalles(Venta $venta): array
    {
        $porcentajeIgv = (float) config('rentaltech.igv', 0.18) * 100;

        if ($venta->detalles->isNotEmpty()) {
            return $venta->detalles->map(fn ($detalle) => [
                'codigo' => $detalle->prod_codigo ?: 'ITEM',
                'descripcion' => $detalle->prod_nombre,
                'unidad' => 'NIU',
                'cantidad' => (float) $detalle->cantidad,
                'mto_valor_unitario' => (float) $detalle->precio_unitario,
                'porcentaje_igv' => $porcentajeIgv,
                'tip_afe_igv' => '10',
            ])->all();
        }

        // Camino de "monto único" (sin líneas de producto): se sintetiza un
        // solo ítem según cuál de los tres montos de la Venta tenga valor.
        $tipAfeIgv = '10';
        $monto = (float) $venta->baseimp;

        if ($monto <= 0 && (float) $venta->exonerado > 0) {
            $tipAfeIgv = '20';
            $monto = (float) $venta->exonerado;
        } elseif ($monto <= 0 && (float) $venta->inafecto > 0) {
            $tipAfeIgv = '30';
            $monto = (float) $venta->inafecto;
        }

        return [[
            'codigo' => 'VENTA',
            'descripcion' => $venta->observaciones ?: 'Venta',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'mto_valor_unitario' => $monto,
            'porcentaje_igv' => $tipAfeIgv === '10' ? $porcentajeIgv : 0,
            'tip_afe_igv' => $tipAfeIgv,
        ]];
    }

    private function esCredito(Venta $venta): bool
    {
        return str_contains(mb_strtolower((string) $venta->condicion_pago), 'cred');
    }

    private function codigoTipoDocumento(?string $numero): string
    {
        return strlen((string) $numero) === 11 ? '6' : '1';
    }

    private function peticion(string $metodo, string $ruta, array $datos = []): ?array
    {
        try {
            $baseUrl = rtrim(config('services.api_go.base_url'), '/');
            $token = config('services.api_go.token');

            $http = Http::timeout(15)->when($token, fn ($http) => $http->withToken($token));

            $respuesta = $metodo === 'get'
                ? $http->get("{$baseUrl}{$ruta}", $datos)
                : $http->post("{$baseUrl}{$ruta}", $datos);

            $cuerpo = $respuesta->json();

            if ($respuesta->failed()) {
                Log::warning('Llamada a API-GO devolvió error', [
                    'ruta' => $ruta,
                    'status' => $respuesta->status(),
                    'body' => $cuerpo,
                ]);

                // API-GO casi siempre responde con un cuerpo JSON explicando
                // el error (ej. certificado no configurado) incluso en 4xx/5xx.
                // Se devuelve igual para que el llamador pueda mostrar el
                // motivo real; solo se devuelve null si no hubo cuerpo.
                return $cuerpo;
            }

            return $cuerpo;
        } catch (\Throwable $e) {
            Log::warning('Llamada a API-GO lanzó excepción', [
                'ruta' => $ruta,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
