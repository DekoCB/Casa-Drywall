<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Services\DocumentService;
use App\Services\FileService;
use App\Models\DispatchGuide;
use App\Http\Requests\IndexDispatchGuideRequest;
use App\Http\Requests\StoreDispatchGuideRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DispatchGuideController extends Controller
{
    use HandlesPdfGeneration;
    
    protected $documentService;
    protected $fileService;

    public function __construct(DocumentService $documentService, FileService $fileService)
    {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
    }

    public function index(IndexDispatchGuideRequest $request): JsonResponse
    {
        try {
            $query = DispatchGuide::with(['company', 'branch', 'destinatario']);

            // Filtros
            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('estado_sunat')) {
                $query->where('estado_sunat', $request->estado_sunat);
            }

            if ($request->has('cod_traslado')) {
                $query->where('cod_traslado', $request->cod_traslado);
            }

            if ($request->has('mod_traslado')) {
                $query->where('mod_traslado', $request->mod_traslado);
            }

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereBetween('fecha_emision', [
                    $request->fecha_desde,
                    $request->fecha_hasta
                ]);
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $dispatchGuides = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Optimizar respuesta para listado
            $data = $dispatchGuides->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'numero_completo' => $guide->numero_completo,
                    'serie' => $guide->serie,
                    'correlativo' => $guide->correlativo,
                    'fecha_emision' => $guide->fecha_emision,
                    'fecha_traslado' => $guide->fecha_traslado,
                    'cod_traslado' => $guide->cod_traslado,
                    'des_traslado' => $guide->des_traslado,
                    'mod_traslado' => $guide->mod_traslado,
                    'estado_sunat' => $guide->estado_sunat,
                    'destinatario' => [
                        'tipo_documento' => $guide->destinatario->tipo_documento ?? null,
                        'numero_documento' => $guide->destinatario->numero_documento ?? null,
                        'razon_social' => $guide->destinatario->razon_social ?? null,
                    ],
                    'traslado' => [
                        'peso_total' => (float) $guide->peso_total,
                        'und_peso_total' => $guide->und_peso_total,
                        'num_bultos' => $guide->num_bultos,
                    ],
                    'archivos' => [
                        'xml_existe' => !empty($guide->xml_path),
                        'cdr_existe' => !empty($guide->cdr_path),
                        'pdf_existe' => !empty($guide->pdf_path),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $data,
                    'current_page' => $dispatchGuides->currentPage(),
                    'per_page' => $dispatchGuides->perPage(),
                    'total' => $dispatchGuides->total(),
                    'last_page' => $dispatchGuides->lastPage(),
                ],
                'message' => 'Guías de remisión obtenidas correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las guías de remisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreDispatchGuideRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Crear la guía de remisión
            $dispatchGuide = $this->documentService->createDispatchGuide($validated);

            // Cargar relaciones necesarias
            $dispatchGuide->load(['company', 'branch', 'destinatario']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $dispatchGuide->id,
                    'numero_completo' => $dispatchGuide->numero_completo,
                    'serie' => $dispatchGuide->serie,
                    'correlativo' => $dispatchGuide->correlativo,
                    'fecha_emision' => $dispatchGuide->fecha_emision,
                    'fecha_traslado' => $dispatchGuide->fecha_traslado,
                    'tipo_documento' => $dispatchGuide->tipo_documento,
                    'empresa' => [
                        'ruc' => $dispatchGuide->company->ruc,
                        'razon_social' => $dispatchGuide->company->razon_social,
                    ],
                    'sucursal' => [
                        'codigo' => $dispatchGuide->branch->codigo,
                        'nombre' => $dispatchGuide->branch->nombre,
                    ],
                    'destinatario' => [
                        'tipo_documento' => $dispatchGuide->destinatario->tipo_documento,
                        'numero_documento' => $dispatchGuide->destinatario->numero_documento,
                        'razon_social' => $dispatchGuide->destinatario->razon_social,
                    ],
                    'traslado' => [
                        'cod_traslado' => $dispatchGuide->cod_traslado,
                        'des_traslado' => $dispatchGuide->des_traslado,
                        'mod_traslado' => $dispatchGuide->mod_traslado,
                        'peso_total' => (float) $dispatchGuide->peso_total,
                        'und_peso_total' => $dispatchGuide->und_peso_total,
                        'num_bultos' => $dispatchGuide->num_bultos,
                    ],
                    'partida' => $dispatchGuide->partida,
                    'llegada' => $dispatchGuide->llegada,
                    'transportista' => $dispatchGuide->transportista,
                    'vehiculo' => $dispatchGuide->vehiculo,
                    'detalles' => $dispatchGuide->detalles ?? [],
                ],
                'message' => 'Guía de remisión creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la guía de remisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $dispatchGuide = DispatchGuide::with(['company', 'branch', 'destinatario'])->findOrFail($id);

            // Parsear respuesta SUNAT si existe
            $sunatResponse = null;
            if (!empty($dispatchGuide->respuesta_sunat)) {
                $respuesta = is_string($dispatchGuide->respuesta_sunat)
                    ? json_decode($dispatchGuide->respuesta_sunat, true)
                    : $dispatchGuide->respuesta_sunat;

                if (is_array($respuesta)) {
                    $sunatResponse = [
                        'codigo' => $respuesta['codRespuesta'] ?? $respuesta['codigo'] ?? null,
                        'descripcion' => $respuesta['desRespuesta'] ?? $respuesta['descripcion'] ?? null,
                        'notas' => $respuesta['notes'] ?? $respuesta['notas'] ?? [],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $dispatchGuide->id,
                    'numero_completo' => $dispatchGuide->numero_completo,
                    'serie' => $dispatchGuide->serie,
                    'correlativo' => $dispatchGuide->correlativo,
                    'tipo_documento' => $dispatchGuide->tipo_documento,
                    'fecha_emision' => $dispatchGuide->fecha_emision,
                    'fecha_traslado' => $dispatchGuide->fecha_traslado,
                    'version' => $dispatchGuide->version,
                    'empresa' => [
                        'ruc' => $dispatchGuide->company->ruc,
                        'razon_social' => $dispatchGuide->company->razon_social,
                        'nombre_comercial' => $dispatchGuide->company->nombre_comercial,
                        'direccion' => $dispatchGuide->company->direccion,
                        'ubigeo' => $dispatchGuide->company->ubigeo,
                        'departamento' => $dispatchGuide->company->departamento,
                        'provincia' => $dispatchGuide->company->provincia,
                        'distrito' => $dispatchGuide->company->distrito,
                    ],
                    'sucursal' => [
                        'codigo' => $dispatchGuide->branch->codigo,
                        'nombre' => $dispatchGuide->branch->nombre,
                        'direccion' => $dispatchGuide->branch->direccion,
                        'ubigeo' => $dispatchGuide->branch->ubigeo,
                        'departamento' => $dispatchGuide->branch->departamento,
                        'provincia' => $dispatchGuide->branch->provincia,
                        'distrito' => $dispatchGuide->branch->distrito,
                    ],
                    'destinatario' => [
                        'tipo_documento' => $dispatchGuide->destinatario->tipo_documento,
                        'numero_documento' => $dispatchGuide->destinatario->numero_documento,
                        'razon_social' => $dispatchGuide->destinatario->razon_social,
                        'direccion' => $dispatchGuide->destinatario->direccion,
                        'email' => $dispatchGuide->destinatario->email,
                        'telefono' => $dispatchGuide->destinatario->telefono,
                    ],
                    'traslado' => [
                        'cod_traslado' => $dispatchGuide->cod_traslado,
                        'des_traslado' => $dispatchGuide->des_traslado,
                        'mod_traslado' => $dispatchGuide->mod_traslado,
                        'peso_total' => (float) $dispatchGuide->peso_total,
                        'und_peso_total' => $dispatchGuide->und_peso_total,
                        'num_bultos' => $dispatchGuide->num_bultos,
                    ],
                    'partida' => $dispatchGuide->partida,
                    'llegada' => $dispatchGuide->llegada,
                    'transportista' => $dispatchGuide->transportista,
                    'vehiculo' => $dispatchGuide->vehiculo,
                    'indicadores' => $dispatchGuide->indicadores,
                    'detalles' => $dispatchGuide->detalles ?? [],
                    'documentos_relacionados' => $dispatchGuide->documentos_relacionados ?? [],
                    'datos_adicionales' => $dispatchGuide->datos_adicionales ?? [],
                    'estado_sunat' => $dispatchGuide->estado_sunat,
                    'sunat' => $sunatResponse,
                    'ticket' => $dispatchGuide->ticket,
                    'archivos' => [
                        'xml' => $dispatchGuide->xml_path,
                        'cdr' => $dispatchGuide->cdr_path,
                        'pdf' => $dispatchGuide->pdf_path,
                        'xml_existe' => !empty($dispatchGuide->xml_path),
                        'cdr_existe' => !empty($dispatchGuide->cdr_path),
                        'pdf_existe' => !empty($dispatchGuide->pdf_path),
                    ],
                    'created_at' => $dispatchGuide->created_at,
                    'updated_at' => $dispatchGuide->updated_at,
                ],
                'message' => 'Guía de remisión obtenida correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Guía de remisión no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function sendToSunat($id): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info("=== CONTROLADOR sendToSunat ===", ['dispatch_guide_id' => $id]);

            $dispatchGuide = DispatchGuide::with(['company', 'branch', 'destinatario'])->findOrFail($id);

            \Illuminate\Support\Facades\Log::info("Guía cargada:", [
                'id' => $dispatchGuide->id,
                'client_id' => $dispatchGuide->client_id,
                'destinatario_loaded' => $dispatchGuide->relationLoaded('destinatario'),
                'destinatario_exists' => $dispatchGuide->destinatario ? 'SI' : 'NO'
            ]);

            if ($dispatchGuide->estado_sunat === 'ACEPTADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La guía de remisión ya fue enviada y aceptada por SUNAT'
                ], 400);
            }

            \Illuminate\Support\Facades\Log::info("Llamando a sendDispatchGuideToSunat...");
            $result = $this->documentService->sendDispatchGuideToSunat($dispatchGuide);

            if ($result['success']) {
                $doc = $result['document'];

                // Disparar webhook de aceptación
                try {
                    $webhookService = app(\App\Services\WebhookService::class);
                    $webhookService->trigger($dispatchGuide->company_id, 'dispatch_guide.accepted', [
                        'document_id' => $doc->id,
                        'numero' => $doc->numero_completo,
                        'serie' => $doc->serie,
                        'correlativo' => $doc->correlativo,
                        'fecha_emision' => $doc->fecha_emision,
                        'fecha_traslado' => $doc->fecha_traslado,
                        'estado_sunat' => $doc->estado_sunat,
                        'ticket' => $result['ticket'] ?? $doc->ticket,
                        'peso_total' => (float) $doc->peso_total,
                        'destinatario' => [
                            'razon_social' => $doc->destinatario->razon_social ?? null,
                            'num_doc' => $doc->destinatario->numero_documento ?? null,
                        ]
                    ]);
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Error al disparar webhook', [
                        'dispatch_guide_id' => $doc->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Parsear respuesta SUNAT si existe
                $sunatResponse = null;
                if (!empty($doc->respuesta_sunat)) {
                    $respuesta = is_string($doc->respuesta_sunat)
                        ? json_decode($doc->respuesta_sunat, true)
                        : $doc->respuesta_sunat;

                    if (is_array($respuesta)) {
                        $sunatResponse = [
                            'codigo' => $respuesta['codRespuesta'] ?? $respuesta['codigo'] ?? null,
                            'descripcion' => $respuesta['desRespuesta'] ?? $respuesta['descripcion'] ?? null,
                            'notas' => $respuesta['notes'] ?? $respuesta['notas'] ?? [],
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $doc->id,
                        'numero_completo' => $doc->numero_completo,
                        'serie' => $doc->serie,
                        'correlativo' => $doc->correlativo,
                        'fecha_emision' => $doc->fecha_emision,
                        'fecha_traslado' => $doc->fecha_traslado,
                        'estado_sunat' => $doc->estado_sunat,
                        'ticket' => $result['ticket'] ?? $doc->ticket,
                        'traslado' => [
                            'peso_total' => (float) $doc->peso_total,
                            'und_peso_total' => $doc->und_peso_total,
                            'num_bultos' => $doc->num_bultos,
                        ],
                        'sunat' => $sunatResponse,
                        'archivos' => [
                            'xml' => $doc->xml_path,
                            'cdr' => $doc->cdr_path,
                            'xml_existe' => !empty($doc->xml_path),
                            'cdr_existe' => !empty($doc->cdr_path),
                        ],
                    ],
                    'message' => 'Guía de remisión enviada correctamente a SUNAT'
                ]);
            } else {
                // Disparar webhook de rechazo
                try {
                    $errorMessage = $result['error'] ?? 'Error desconocido';
                    $webhookService = app(\App\Services\WebhookService::class);
                    $webhookService->trigger($dispatchGuide->company_id, 'dispatch_guide.rejected', [
                        'document_id' => $dispatchGuide->id,
                        'numero' => $dispatchGuide->numero_completo,
                        'serie' => $dispatchGuide->serie,
                        'correlativo' => $dispatchGuide->correlativo,
                        'fecha_emision' => $dispatchGuide->fecha_emision,
                        'fecha_traslado' => $dispatchGuide->fecha_traslado,
                        'peso_total' => (float) $dispatchGuide->peso_total,
                        'error_message' => is_string($errorMessage) ? $errorMessage : json_encode($errorMessage),
                        'destinatario' => [
                            'razon_social' => $dispatchGuide->destinatario->razon_social ?? null,
                            'num_doc' => $dispatchGuide->destinatario->numero_documento ?? null,
                        ]
                    ]);
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Error al disparar webhook de rechazo', [
                        'dispatch_guide_id' => $dispatchGuide->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar guía a SUNAT',
                    'error' => $result['error'] ?? 'Error desconocido'
                ], 400);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("=== ERROR EN CONTROLADOR ===", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el envío a SUNAT',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkStatus($id): JsonResponse
    {
        try {
            $dispatchGuide = DispatchGuide::with(['company', 'branch', 'destinatario'])->findOrFail($id);

            if (empty($dispatchGuide->ticket)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La guía no tiene un ticket para consultar'
                ], 400);
            }

            $result = $this->documentService->checkDispatchGuideStatus($dispatchGuide);

            if ($result['success']) {
                $doc = $result['document'];

                // Parsear respuesta SUNAT si existe
                $sunatResponse = null;
                if (!empty($doc->respuesta_sunat)) {
                    $respuesta = is_string($doc->respuesta_sunat)
                        ? json_decode($doc->respuesta_sunat, true)
                        : $doc->respuesta_sunat;

                    if (is_array($respuesta)) {
                        $sunatResponse = [
                            'codigo' => $respuesta['codRespuesta'] ?? $respuesta['codigo'] ?? null,
                            'descripcion' => $respuesta['desRespuesta'] ?? $respuesta['descripcion'] ?? null,
                            'notas' => $respuesta['notes'] ?? $respuesta['notas'] ?? [],
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $doc->id,
                        'numero_completo' => $doc->numero_completo,
                        'serie' => $doc->serie,
                        'correlativo' => $doc->correlativo,
                        'fecha_emision' => $doc->fecha_emision,
                        'fecha_traslado' => $doc->fecha_traslado,
                        'estado_sunat' => $doc->estado_sunat,
                        'ticket' => $doc->ticket,
                        'sunat' => $sunatResponse,
                        'archivos' => [
                            'xml' => $doc->xml_path,
                            'cdr' => $doc->cdr_path,
                            'xml_existe' => !empty($doc->xml_path),
                            'cdr_existe' => !empty($doc->cdr_path),
                        ],
                    ],
                    'message' => 'Estado de la guía consultado correctamente'
                ]);
            } else {
                $errorMessage = 'Error desconocido';
                if (isset($result['error'])) {
                    if (is_object($result['error']) && method_exists($result['error'], 'getMessage')) {
                        $errorMessage = $result['error']->getMessage();
                    } elseif (is_string($result['error'])) {
                        $errorMessage = $result['error'];
                    } elseif (is_array($result['error'])) {
                        $errorMessage = json_encode($result['error']);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Error al consultar estado: ' . $errorMessage
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadXml($id)
    {
        try {
            $dispatchGuide = DispatchGuide::findOrFail($id);
            
            $download = $this->fileService->downloadXml($dispatchGuide);
            
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
            $dispatchGuide = DispatchGuide::findOrFail($id);
            
            $download = $this->fileService->downloadCdr($dispatchGuide);
            
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
        $dispatchGuide = DispatchGuide::findOrFail($id);
        return $this->downloadDocumentPdf($dispatchGuide, $request);
    }

    public function generatePdf($id, Request $request)
    {
        $dispatchGuide = DispatchGuide::with(['company', 'branch', 'destinatario'])->findOrFail($id);
        return $this->generateDocumentPdf($dispatchGuide, 'dispatch-guide', $request);
    }

    public function getTransferReasons(): JsonResponse
    {
        $reasons = [
            ['code' => '01', 'name' => 'Venta'],
            ['code' => '02', 'name' => 'Compra'],
            ['code' => '03', 'name' => 'Venta con entrega a terceros'],
            ['code' => '04', 'name' => 'Traslado entre establecimientos de la misma empresa'],
            ['code' => '05', 'name' => 'Consignación'],
            ['code' => '06', 'name' => 'Devolución'],
            ['code' => '07', 'name' => 'Recojo de bienes transformados'],
            ['code' => '08', 'name' => 'Importación'],
            ['code' => '09', 'name' => 'Exportación'],
            ['code' => '13', 'name' => 'Otros'],
            ['code' => '14', 'name' => 'Venta sujeta a confirmación del comprador'],
            ['code' => '18', 'name' => 'Traslado de bienes para transformación'],
            ['code' => '19', 'name' => 'Traslado de bienes desde un centro de acopio'],
        ];

        return response()->json([
            'success' => true,
            'data' => $reasons,
            'message' => 'Motivos de traslado obtenidos correctamente'
        ]);
    }

    public function getTransportModes(): JsonResponse
    {
        $modes = [
            ['code' => '01', 'name' => 'Transporte público'],
            ['code' => '02', 'name' => 'Transporte privado'],
        ];

        return response()->json([
            'success' => true,
            'data' => $modes,
            'message' => 'Modalidades de transporte obtenidas correctamente'
        ]);
    }
}