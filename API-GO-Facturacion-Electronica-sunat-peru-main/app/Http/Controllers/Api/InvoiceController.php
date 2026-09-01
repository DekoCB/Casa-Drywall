<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Services\DocumentService;
use App\Services\FileService;
use App\Models\Invoice;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Requests\IndexInvoiceRequest;
use App\Exceptions\SunatException;
use App\Exceptions\DocumentAlreadySentException;
use App\Jobs\SendDocumentToSunat;
use App\Http\Resources\InvoiceResource;
use App\Repositories\InvoiceRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    use HandlesPdfGeneration;
    protected $documentService;
    protected $fileService;
    protected $invoiceRepository;

    public function __construct(
        DocumentService $documentService,
        FileService $fileService,
        InvoiceRepository $invoiceRepository
    ) {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
        $this->invoiceRepository = $invoiceRepository;
    }

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        try {
            // Preparar filtros para el repositorio
            $filters = array_filter([
                'company_id' => $request->company_id,
                'branch_id' => $request->branch_id,
                'estado_sunat' => $request->estado_sunat,
                'fecha_inicio' => $request->fecha_desde,
                'fecha_fin' => $request->fecha_hasta,
                'moneda' => $request->moneda,
                'numero' => $request->numero,
                'search' => $request->search,
                'per_page' => $request->get('per_page', 15)
            ]);

            // Usar el repositorio para obtener facturas con filtros
            $invoices = $this->invoiceRepository->getByDateRange($filters);

            // Transformar datos para respuesta optimizada
            $data = collect($invoices->items())->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'numero_completo' => $invoice->numero_completo,
                    'serie' => $invoice->serie,
                    'correlativo' => $invoice->correlativo,
                    'fecha_emision' => $invoice->fecha_emision,
                    'moneda' => $invoice->moneda,
                    'estado_sunat' => $invoice->estado_sunat,

                    'cliente' => [
                        'tipo_documento' => $invoice->client->tipo_documento ?? null,
                        'numero_documento' => $invoice->client->numero_documento ?? null,
                        'razon_social' => $invoice->client->razon_social ?? null
                    ],

                    'totales' => [
                        'gravada' => (float) $invoice->mto_oper_gravadas,
                        'igv' => (float) $invoice->mto_igv,
                        'total' => (float) $invoice->mto_imp_venta
                    ],

                    'archivos' => [
                        'xml_existe' => !empty($invoice->xml_path),
                        'cdr_existe' => !empty($invoice->cdr_path)
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
                'message' => 'Facturas obtenidas correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las facturas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Crear la factura
            $invoice = $this->documentService->createInvoice($validated);

            // Respuesta simplificada con solo datos esenciales
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $invoice->id,
                    'numero_completo' => $invoice->numero_completo,
                    'serie' => $invoice->serie,
                    'correlativo' => $invoice->correlativo,
                    'fecha_emision' => $invoice->fecha_emision,
                    'moneda' => $invoice->moneda,
                    'estado_sunat' => $invoice->estado_sunat,

                    // Datos de empresa y sucursal mínimos
                    'empresa' => [
                        'ruc' => $invoice->company->ruc,
                        'razon_social' => $invoice->company->razon_social
                    ],
                    'sucursal' => [
                        'codigo' => $invoice->branch->codigo,
                        'nombre' => $invoice->branch->nombre
                    ],

                    // Cliente
                    'cliente' => [
                        'tipo_documento' => $invoice->client->tipo_documento,
                        'numero_documento' => $invoice->client->numero_documento,
                        'razon_social' => $invoice->client->razon_social
                    ],

                    // Totales principales
                    'totales' => [
                        'gravada' => (float) $invoice->mto_oper_gravadas,
                        'exonerada' => (float) $invoice->mto_oper_exoneradas,
                        'inafecta' => (float) $invoice->mto_oper_inafectas,
                        'igv' => (float) $invoice->mto_igv,
                        'isc' => (float) $invoice->mto_isc,
                        'icbper' => (float) $invoice->mto_icbper,
                        'total' => (float) $invoice->mto_imp_venta
                    ],

                    // Detalles (si existen)
                    'detalles' => $invoice->detalles,
                    'leyendas' => $invoice->leyendas
                ],
                'message' => 'Factura creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la factura',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateInvoiceRequest $request, $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Actualizar la factura
            $invoice = $this->documentService->updateInvoice($id, $validated);

            // Respuesta simplificada
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $invoice->id,
                    'numero_completo' => $invoice->numero_completo,
                    'estado_sunat' => $invoice->estado_sunat,
                    'fecha_emision' => $invoice->fecha_emision,
                    'totales' => [
                        'gravada' => (float) $invoice->mto_oper_gravadas,
                        'igv' => (float) $invoice->mto_igv,
                        'total' => (float) $invoice->mto_imp_venta
                    ]
                ],
                'message' => 'Factura actualizada correctamente. Estado restablecido a PENDIENTE para reenvío.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la factura',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            // Usar el repositorio para obtener la factura con todas las relaciones
            $invoice = $this->invoiceRepository->findWithRelations($id);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada'
                ], 404);
            }

            // Parsear respuesta SUNAT si existe
            $respuestaSunat = null;
            if ($invoice->respuesta_sunat) {
                $respuestaSunat = json_decode($invoice->respuesta_sunat, true);
            }

            // Respuesta optimizada con información completa
            return response()->json([
                'success' => true,
                'data' => [
                    // Información principal
                    'id' => $invoice->id,
                    'numero_completo' => $invoice->numero_completo,
                    'serie' => $invoice->serie,
                    'correlativo' => $invoice->correlativo,
                    'tipo_documento' => $invoice->tipo_documento,
                    'fecha_emision' => $invoice->fecha_emision,
                    'fecha_vencimiento' => $invoice->fecha_vencimiento,
                    'moneda' => $invoice->moneda,
                    'tipo_operacion' => $invoice->tipo_operacion,

                    // Empresa (datos esenciales)
                    'empresa' => [
                        'ruc' => $invoice->company->ruc,
                        'razon_social' => $invoice->company->razon_social,
                        'nombre_comercial' => $invoice->company->nombre_comercial,
                        'direccion' => $invoice->company->direccion,
                        'ubigeo' => $invoice->company->ubigeo,
                        'telefono' => $invoice->company->telefono,
                        'email' => $invoice->company->email,
                        'logo_path' => $invoice->company->logo_path
                    ],

                    // Sucursal (datos esenciales)
                    'sucursal' => [
                        'codigo' => $invoice->branch->codigo,
                        'nombre' => $invoice->branch->nombre,
                        'direccion' => $invoice->branch->direccion,
                        'ubigeo' => $invoice->branch->ubigeo,
                        'distrito' => $invoice->branch->distrito,
                        'provincia' => $invoice->branch->provincia,
                        'departamento' => $invoice->branch->departamento
                    ],

                    // Cliente (datos esenciales)
                    'cliente' => [
                        'tipo_documento' => $invoice->client->tipo_documento,
                        'numero_documento' => $invoice->client->numero_documento,
                        'razon_social' => $invoice->client->razon_social,
                        'nombre_comercial' => $invoice->client->nombre_comercial ?? null,
                        'direccion' => $invoice->client->direccion,
                        'email' => $invoice->client->email ?? null,
                        'telefono' => $invoice->client->telefono ?? null
                    ],

                    // Forma de pago
                    'forma_pago' => [
                        'tipo' => $invoice->forma_pago_tipo,
                        'cuotas' => $invoice->forma_pago_cuotas
                    ],

                    // Totales
                    'totales' => [
                        'valor_venta' => (float) $invoice->valor_venta,
                        'gravada' => (float) $invoice->mto_oper_gravadas,
                        'exonerada' => (float) $invoice->mto_oper_exoneradas,
                        'inafecta' => (float) $invoice->mto_oper_inafectas,
                        'exportacion' => (float) $invoice->mto_oper_exportacion,
                        'gratuita' => (float) $invoice->mto_oper_gratuitas,
                        'igv' => (float) $invoice->mto_igv,
                        'isc' => (float) $invoice->mto_isc,
                        'icbper' => (float) $invoice->mto_icbper,
                        'total_impuestos' => (float) $invoice->total_impuestos,
                        'descuentos' => (float) $invoice->mto_descuentos,
                        'total' => (float) $invoice->mto_imp_venta
                    ],

                    // Detalles del documento
                    'detalles' => $invoice->detalles,
                    'leyendas' => $invoice->leyendas,

                    // Documentos relacionados
                    'guias' => $invoice->guias,
                    'documentos_relacionados' => $invoice->documentos_relacionados,

                    // Estado SUNAT
                    'estado_sunat' => $invoice->estado_sunat,
                    'sunat' => [
                        'codigo' => $respuestaSunat['code'] ?? null,
                        'descripcion' => $respuestaSunat['description'] ?? null,
                        'notas' => $respuestaSunat['notes'] ?? []
                    ],

                    // Archivos
                    'archivos' => [
                        'xml' => $invoice->xml_path,
                        'cdr' => $invoice->cdr_path,
                        'pdf' => $invoice->pdf_path,
                        'hash' => $invoice->codigo_hash
                    ],

                    // Metadatos
                    'usuario_creacion' => $invoice->usuario_creacion,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at
                ],
                'message' => 'Factura obtenida correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Factura no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function sendToSunat($id): JsonResponse
    {
        try {
            $invoice = $this->invoiceRepository->findWithRelations($id);

            if (!$invoice) {
                throw new SunatException(
                    userMessage: 'Factura no encontrada',
                    sunatCode: 'NOT_FOUND',
                    context: ['invoice_id' => $id],
                    httpCode: 404
                );
            }

            // Validar que no haya sido ACEPTADO (permitir reenvío de RECHAZADOS y PENDIENTES)
            if ($invoice->estado_sunat === 'ACEPTADO') {
                throw new DocumentAlreadySentException(
                    'FACTURA',
                    $invoice->numero_completo
                );
            }

            // Log del reenvío si es RECHAZADO
            if ($invoice->estado_sunat === 'RECHAZADO') {
                Log::info('Reenviando factura rechazada a SUNAT', [
                    'invoice_id' => $invoice->id,
                    'numero' => $invoice->numero_completo,
                    'rechazo_anterior' => $invoice->respuesta_sunat
                ]);
            }

            // Intentar enviar a SUNAT
            $result = $this->documentService->sendToSunat($invoice, 'invoice');

            if ($result['success']) {
                Log::info('Factura enviada exitosamente a SUNAT', [
                    'invoice_id' => $invoice->id,
                    'numero' => $invoice->numero_completo,
                    'company_id' => $invoice->company_id
                ]);

                $doc = $result['document'];

                // Disparar webhook
                try {
                    $webhookService = app(\App\Services\WebhookService::class);
                    $webhookService->trigger($invoice->company_id, 'invoice.accepted', [
                        'document_id' => $doc->id,
                        'numero' => $doc->numero_completo,
                        'serie' => $doc->serie,
                        'correlativo' => $doc->correlativo,
                        'fecha_emision' => $doc->fecha_emision,
                        'monto' => (float) $doc->mto_imp_venta,
                        'moneda' => $doc->moneda,
                        'estado_sunat' => $doc->estado_sunat,
                        'cliente' => [
                            'razon_social' => $doc->client->razon_social ?? null,
                            'num_doc' => $doc->client->numero_documento ?? null,
                        ]
                    ]);

                    // Procesar webhooks inmediatamente
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    Log::warning('Error al disparar webhook', [
                        'invoice_id' => $doc->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Parsear respuesta SUNAT
                $respuestaSunat = json_decode($doc->respuesta_sunat, true);

                // Respuesta optimizada
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $doc->id,
                        'numero_completo' => $doc->numero_completo,
                        'serie' => $doc->serie,
                        'correlativo' => $doc->correlativo,
                        'fecha_emision' => $doc->fecha_emision,
                        'moneda' => $doc->moneda,
                        'estado_sunat' => $doc->estado_sunat,

                        'totales' => [
                            'gravada' => (float) $doc->mto_oper_gravadas,
                            'igv' => (float) $doc->mto_igv,
                            'total' => (float) $doc->mto_imp_venta
                        ],

                        'sunat' => [
                            'codigo' => $respuestaSunat['code'] ?? null,
                            'descripcion' => $respuestaSunat['description'] ?? null,
                            'notas' => $respuestaSunat['notes'] ?? []
                        ],

                        'archivos' => [
                            'xml' => $doc->xml_path,
                            'cdr' => $doc->cdr_path,
                            'hash' => $doc->codigo_hash
                        ]
                    ],
                    'message' => 'Factura enviada y aceptada por SUNAT'
                ]);
            } else {
                // Extraer información del error
                $errorCode = 'UNKNOWN';
                $errorMessage = 'Error desconocido al comunicarse con SUNAT';

                if (is_object($result['error'])) {
                    if (method_exists($result['error'], 'getCode')) {
                        $errorCode = $result['error']->getCode();
                    } elseif (property_exists($result['error'], 'code')) {
                        $errorCode = $result['error']->code;
                    }

                    if (method_exists($result['error'], 'getMessage')) {
                        $errorMessage = $result['error']->getMessage();
                    } elseif (property_exists($result['error'], 'message')) {
                        $errorMessage = $result['error']->message;
                    }
                }

                // Disparar webhook de rechazo
                try {
                    $webhookService = app(\App\Services\WebhookService::class);
                    $webhookService->trigger($invoice->company_id, 'invoice.rejected', [
                        'document_id' => $invoice->id,
                        'numero' => $invoice->numero_completo,
                        'serie' => $invoice->serie,
                        'correlativo' => $invoice->correlativo,
                        'fecha_emision' => $invoice->fecha_emision,
                        'monto' => (float) $invoice->mto_imp_venta,
                        'moneda' => $invoice->moneda,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'cliente' => [
                            'razon_social' => $invoice->client->razon_social ?? null,
                            'num_doc' => $invoice->client->numero_documento ?? null,
                        ]
                    ]);

                    // Procesar webhooks inmediatamente
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    Log::warning('Error al disparar webhook de rechazo', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage()
                    ]);
                }

                throw new SunatException(
                    userMessage: "SUNAT rechazó el documento: {$errorMessage}",
                    sunatCode: (string)$errorCode,
                    context: [
                        'invoice_id' => $invoice->id,
                        'numero' => $invoice->numero_completo,
                        'company_id' => $invoice->company_id
                    ]
                );
            }

        } catch (ModelNotFoundException $e) {
            throw new SunatException(
                userMessage: 'Factura no encontrada',
                sunatCode: 'NOT_FOUND',
                context: ['invoice_id' => $id],
                httpCode: 404
            );
        } catch (SunatException | DocumentAlreadySentException $e) {
            throw $e; // Re-lanzar excepciones SUNAT para que el handler las procese
        } catch (\Throwable $e) {
            Log::critical('Error inesperado al enviar factura a SUNAT', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new SunatException(
                userMessage: 'Error interno al procesar el envío. Por favor contacte con soporte técnico.',
                sunatCode: 'INTERNAL_ERROR',
                context: [
                    'invoice_id' => $id,
                    'error_class' => get_class($e)
                ],
                httpCode: 500
            );
        }
    }

    public function downloadXml($id)
    {
        try {
            $invoice = $this->invoiceRepository->findOrFail($id);
            
            $download = $this->fileService->downloadXml($invoice);
            
            if (!$download) {
                return response()->json([
                    'success' => false,
                    'message' => 'XML no encontrado'
                ], 404);
            }
            
            return $download;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar XML',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadCdr($id)
    {
        try {
            $invoice = $this->invoiceRepository->findOrFail($id);
            
            $download = $this->fileService->downloadCdr($invoice);
            
            if (!$download) {
                return response()->json([
                    'success' => false,
                    'message' => 'CDR no encontrado'
                ], 404);
            }
            
            return $download;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar CDR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadPdf($id, Request $request)
    {
        $invoice = $this->invoiceRepository->findOrFail($id);
        return $this->downloadDocumentPdf($invoice, $request);
    }

    public function generatePdf($id, Request $request)
    {
        $invoice = $this->invoiceRepository->findWithRelations($id);
        return $this->generateDocumentPdf($invoice, 'invoice', $request);
    }

    /**
     * Enviar factura a SUNAT de forma asíncrona (usando colas)
     *
     * @param int $id ID de la factura
     * @return JsonResponse
     */
    public function sendToSunatAsync($id): JsonResponse
    {
        try {
            $invoice = $this->invoiceRepository->findWithRelations($id);

            if (!$invoice) {
                throw new SunatException(
                    userMessage: 'Factura no encontrada',
                    sunatCode: 'NOT_FOUND',
                    context: ['invoice_id' => $id],
                    httpCode: 404
                );
            }

            // Validar que no haya sido enviado previamente
            if ($invoice->estado_sunat === 'ACEPTADO') {
                throw new DocumentAlreadySentException(
                    'FACTURA',
                    $invoice->numero_completo
                );
            }

            // Marcar como en proceso
            $invoice->update(['estado_sunat' => 'EN_COLA']);

            // Despachar job a la cola
            SendDocumentToSunat::dispatch($invoice, 'invoice');

            Log::info('Factura agregada a cola para envío a SUNAT', [
                'invoice_id' => $invoice->id,
                'numero' => $invoice->numero_completo
            ]);

            $doc = $invoice->fresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $doc->id,
                    'numero_completo' => $doc->numero_completo,
                    'serie' => $doc->serie,
                    'correlativo' => $doc->correlativo,
                    'fecha_emision' => $doc->fecha_emision,
                    'moneda' => $doc->moneda,
                    'estado_sunat' => $doc->estado_sunat,

                    'totales' => [
                        'gravada' => (float) $doc->mto_oper_gravadas,
                        'igv' => (float) $doc->mto_igv,
                        'total' => (float) $doc->mto_imp_venta
                    ]
                ],
                'message' => 'Factura agregada a la cola de envío. Recibirá una notificación cuando se complete el proceso.'
            ], 202); // 202 Accepted

        } catch (ModelNotFoundException $e) {
            throw new SunatException(
                userMessage: 'Factura no encontrada',
                sunatCode: 'NOT_FOUND',
                context: ['invoice_id' => $id],
                httpCode: 404
            );
        } catch (SunatException | DocumentAlreadySentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::critical('Error al agregar factura a cola', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            throw new SunatException(
                userMessage: 'Error al procesar la solicitud de envío.',
                sunatCode: 'QUEUE_ERROR',
                context: ['invoice_id' => $id],
                httpCode: 500
            );
        }
    }

    protected function processInvoiceDetails(array $detalles, string $tipoOperacion = '0101'): array
    {
        // Para exportaciones (0200), no se debe calcular IGV
        $isExportacion = $tipoOperacion === '0200';

        foreach ($detalles as &$detalle) {
            $cantidad = $detalle['cantidad'];
            $valorUnitario = $detalle['mto_valor_unitario'];
            $porcentajeIgv = $isExportacion ? 0 : ($detalle['porcentaje_igv'] ?? 0);
            $tipAfeIgv = $isExportacion ? '40' : ($detalle['tip_afe_igv'] ?? '10'); // 40 = Exportación

            // Actualizar tipo de afectación para exportaciones
            $detalle['tip_afe_igv'] = $tipAfeIgv;
            $detalle['porcentaje_igv'] = $porcentajeIgv;

            // Calcular valor de venta
            $valorVenta = $cantidad * $valorUnitario;
            $detalle['mto_valor_venta'] = $valorVenta;

            // Para exportaciones - según ejemplo de Greenter
            if ($isExportacion) {
                $detalle['mto_base_igv'] = $valorVenta; // Base IGV = valor venta en exportaciones
                $detalle['igv'] = 0;
                $detalle['total_impuestos'] = 0;
                $detalle['mto_precio_unitario'] = $valorUnitario;
            } else {
                // Calcular base imponible IGV
                $baseIgv = in_array($tipAfeIgv, ['10', '17']) ? $valorVenta : 0;
                $detalle['mto_base_igv'] = $baseIgv;

                // Calcular IGV
                $igv = ($baseIgv * $porcentajeIgv) / 100;
                $detalle['igv'] = $igv;

                // Calcular impuestos totales del item
                $detalle['total_impuestos'] = $igv;

                // Calcular precio unitario (incluye impuestos)
                $detalle['mto_precio_unitario'] = ($valorVenta + $igv) / $cantidad;
            }
        }

        return $detalles;
    }
}