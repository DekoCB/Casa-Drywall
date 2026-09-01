<?php

namespace App\Jobs;

use App\Services\DocumentService;
use App\Services\WebhookService;
use App\Events\DocumentSentToSunat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDocumentToSunat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos antes de fallar
     */
    public $tries = 3;

    /**
     * Tiempos de espera entre reintentos (en segundos)
     */
    public $backoff = [30, 60, 120];

    /**
     * Timeout del job en segundos
     */
    public $timeout = 300; // 5 minutos

    /**
     * Crear nueva instancia del job
     *
     * @param mixed $document Modelo del documento (Invoice, Boleta, etc.)
     * @param string $documentType Tipo de documento ('invoice', 'boleta', etc.)
     */
    public function __construct(
        public $document,
        public string $documentType
    ) {
        // Configurar cola específica para envíos a SUNAT
        $this->onQueue('sunat-send');
    }

    /**
     * Ejecutar el job
     */
    public function handle(DocumentService $documentService): void
    {
        Log::info("Iniciando envío a SUNAT", [
            'document_type' => $this->documentType,
            'document_id' => $this->document->id,
            'numero' => $this->document->numero_completo,
            'attempt' => $this->attempts()
        ]);

        try {
            // Enviar documento a SUNAT
            $result = $documentService->sendToSunat($this->document, $this->documentType);

            // Disparar evento de documento enviado
            event(new DocumentSentToSunat(
                $this->document,
                $this->documentType,
                $result
            ));

            if ($result['success']) {
                Log::info("Documento enviado exitosamente a SUNAT", [
                    'document_type' => $this->documentType,
                    'document_id' => $this->document->id,
                    'numero' => $this->document->numero_completo
                ]);

                // Disparar webhook de documento aceptado
                $this->triggerWebhook('accepted', $result);
            } else {
                Log::warning("SUNAT rechazó el documento", [
                    'document_type' => $this->documentType,
                    'document_id' => $this->document->id,
                    'numero' => $this->document->numero_completo,
                    'error' => $result['error']
                ]);

                // Disparar webhook de documento rechazado
                $this->triggerWebhook('rejected', $result);

                // Si fue rechazado, no reintentar
                $this->delete();
            }

        } catch (\Throwable $e) {
            Log::error("Error al enviar documento a SUNAT", [
                'document_type' => $this->documentType,
                'document_id' => $this->document->id,
                'numero' => $this->document->numero_completo,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Re-lanzar excepción para que Laravel maneje los reintentos
            throw $e;
        }
    }

    /**
     * Manejar falla del job después de todos los intentos
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("Job de envío a SUNAT falló definitivamente", [
            'document_type' => $this->documentType,
            'document_id' => $this->document->id,
            'numero' => $this->document->numero_completo,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Actualizar estado del documento
        $this->document->update([
            'estado_sunat' => 'ERROR',
            'respuesta_sunat' => json_encode([
                'error' => $exception->getMessage(),
                'code' => 'JOB_FAILED',
                'attempts' => $this->attempts()
            ])
        ]);

        // Notificar al usuario o administrador (implementar según necesidad)
        // Mail::to('admin@empresa.com')->send(new DocumentSendFailed($this->document));
    }

    /**
     * Determinar el tags para el job (útil para monitoreo)
     */
    public function tags(): array
    {
        return [
            'sunat-send',
            $this->documentType,
            "company:{$this->document->company_id}",
            "document:{$this->document->id}"
        ];
    }

    /**
     * Disparar webhook para el documento
     */
    private function triggerWebhook(string $status, array $result): void
    {
        try {
            $webhookService = app(WebhookService::class);

            // Mapear tipo de documento a nombre de evento
            $eventMap = [
                'invoice' => 'invoice',
                'boleta' => 'boleta',
                'credit_note' => 'credit_note',
                'debit_note' => 'debit_note',
                'dispatch_guide' => 'dispatch_guide',
            ];

            $eventPrefix = $eventMap[$this->documentType] ?? $this->documentType;
            $eventName = "{$eventPrefix}.{$status}";

            // Construir payload base
            $payload = [
                'document_id' => $this->document->id,
                'numero' => $this->document->numero_completo,
                'serie' => $this->document->serie,
                'correlativo' => $this->document->correlativo,
                'fecha_emision' => $this->document->fecha_emision,
                'estado_sunat' => $this->document->estado_sunat,
            ];

            // Agregar información específica según tipo de documento
            if ($this->documentType === 'dispatch_guide') {
                // Guías de remisión
                $payload['fecha_traslado'] = $this->document->fecha_traslado;
                $payload['peso_total'] = (float) $this->document->peso_total;
                $payload['ticket'] = $result['ticket'] ?? $this->document->ticket;

                if ($this->document->destinatario) {
                    $payload['destinatario'] = [
                        'razon_social' => $this->document->destinatario->razon_social ?? null,
                        'num_doc' => $this->document->destinatario->numero_documento ?? null,
                    ];
                }
            } else {
                // Facturas, boletas, notas de crédito/débito
                $payload['monto'] = (float) $this->document->mto_imp_venta;
                $payload['moneda'] = $this->document->moneda;

                if ($this->document->client) {
                    $payload['cliente'] = [
                        'razon_social' => $this->document->client->razon_social ?? null,
                        'num_doc' => $this->document->client->numero_documento ?? null,
                    ];
                }

                // Para notas de crédito/débito agregar documento afectado
                if (in_array($this->documentType, ['credit_note', 'debit_note'])) {
                    $payload['documento_afectado'] = $this->document->serie_doc_afectado . '-' . $this->document->num_doc_afectado;
                }
            }

            // Si fue rechazado, agregar información de error
            if ($status === 'rejected') {
                $errorMessage = $result['error'] ?? 'Error desconocido';
                $errorCode = null;

                // Extraer código de error si está presente
                if (preg_match('/(\d{4})\s*[-:]\s*(.+)/', $errorMessage, $matches)) {
                    $errorCode = $matches[1];
                    $errorMessage = trim($matches[2]);
                }

                $payload['error_code'] = $errorCode;
                $payload['error_message'] = $errorMessage;
            }

            // Disparar webhook
            $webhookService->trigger($this->document->company_id, $eventName, $payload);

            // Procesar entregas pendientes inmediatamente
            $webhookService->processPendingDeliveries();

            Log::info("Webhook disparado exitosamente", [
                'event' => $eventName,
                'document_id' => $this->document->id,
            ]);

        } catch (\Exception $e) {
            Log::warning("Error al disparar webhook desde job", [
                'document_type' => $this->documentType,
                'document_id' => $this->document->id,
                'event' => $eventName ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }
}
