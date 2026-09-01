<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Services\DocumentService;
use App\Services\FileService;
use App\Models\CreditNote;
use App\Http\Requests\IndexCreditNoteRequest;
use App\Http\Requests\StoreCreditNoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CreditNoteController extends Controller
{
    use HandlesPdfGeneration;
    
    protected $documentService;
    protected $fileService;

    public function __construct(DocumentService $documentService, FileService $fileService)
    {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
    }

    public function index(IndexCreditNoteRequest $request): JsonResponse
    {
        try {
            $query = CreditNote::with(['company', 'branch', 'client']);

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

            if ($request->has('tipo_doc_afectado')) {
                $query->where('tipo_doc_afectado', $request->tipo_doc_afectado);
            }

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereBetween('fecha_emision', [
                    $request->fecha_desde,
                    $request->fecha_hasta
                ]);
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $creditNotes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transformar datos para respuesta optimizada
            $data = collect($creditNotes->items())->map(function ($creditNote) {
                return [
                    'id' => $creditNote->id,
                    'numero_completo' => $creditNote->numero_completo,
                    'serie' => $creditNote->serie,
                    'correlativo' => $creditNote->correlativo,
                    'fecha_emision' => $creditNote->fecha_emision,
                    'moneda' => $creditNote->moneda,
                    'estado_sunat' => $creditNote->estado_sunat,
                    'tipo_nota' => $creditNote->tipo_nota,

                    'cliente' => [
                        'tipo_documento' => $creditNote->client->tipo_documento ?? null,
                        'numero_documento' => $creditNote->client->numero_documento ?? null,
                        'razon_social' => $creditNote->client->razon_social ?? null
                    ],

                    'documento_afectado' => [
                        'tipo' => $creditNote->tipo_doc_afectado,
                        'numero' => $creditNote->serie_doc_afectado . '-' . $creditNote->num_doc_afectado
                    ],

                    'totales' => [
                        'gravada' => (float) $creditNote->mto_oper_gravadas,
                        'igv' => (float) $creditNote->mto_igv,
                        'total' => (float) $creditNote->mto_imp_venta
                    ],

                    'archivos' => [
                        'xml_existe' => !empty($creditNote->xml_path),
                        'cdr_existe' => !empty($creditNote->cdr_path)
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $creditNotes->currentPage(),
                    'last_page' => $creditNotes->lastPage(),
                    'per_page' => $creditNotes->perPage(),
                    'total' => $creditNotes->total()
                ],
                'message' => 'Notas de crédito obtenidas correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notas de crédito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Crear la nota de crédito
            $creditNote = $this->documentService->createCreditNote($validated);

            // Respuesta simplificada
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $creditNote->id,
                    'numero_completo' => $creditNote->numero_completo,
                    'serie' => $creditNote->serie,
                    'correlativo' => $creditNote->correlativo,
                    'fecha_emision' => $creditNote->fecha_emision,
                    'moneda' => $creditNote->moneda,
                    'estado_sunat' => $creditNote->estado_sunat,
                    'tipo_nota' => $creditNote->tipo_nota,

                    'empresa' => [
                        'ruc' => $creditNote->company->ruc,
                        'razon_social' => $creditNote->company->razon_social
                    ],
                    'sucursal' => [
                        'codigo' => $creditNote->branch->codigo,
                        'nombre' => $creditNote->branch->nombre
                    ],
                    'cliente' => [
                        'tipo_documento' => $creditNote->client->tipo_documento,
                        'numero_documento' => $creditNote->client->numero_documento,
                        'razon_social' => $creditNote->client->razon_social
                    ],

                    'documento_afectado' => [
                        'tipo' => $creditNote->tipo_doc_afectado,
                        'serie' => $creditNote->serie_doc_afectado,
                        'numero' => $creditNote->num_doc_afectado
                    ],

                    'totales' => [
                        'gravada' => (float) $creditNote->mto_oper_gravadas,
                        'igv' => (float) $creditNote->mto_igv,
                        'total' => (float) $creditNote->mto_imp_venta
                    ],

                    'detalles' => $creditNote->detalles,
                    'leyendas' => $creditNote->leyendas
                ],
                'message' => 'Nota de crédito creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la nota de crédito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);

            // Parsear respuesta SUNAT si existe
            $respuestaSunat = null;
            if ($creditNote->respuesta_sunat) {
                $respuestaSunat = json_decode($creditNote->respuesta_sunat, true);
            }

            // Respuesta optimizada con información completa
            return response()->json([
                'success' => true,
                'data' => [
                    // Información principal
                    'id' => $creditNote->id,
                    'numero_completo' => $creditNote->numero_completo,
                    'serie' => $creditNote->serie,
                    'correlativo' => $creditNote->correlativo,
                    'tipo_documento' => $creditNote->tipo_documento,
                    'fecha_emision' => $creditNote->fecha_emision,
                    'moneda' => $creditNote->moneda,
                    'tipo_nota' => $creditNote->tipo_nota,

                    // Empresa (datos esenciales)
                    'empresa' => [
                        'ruc' => $creditNote->company->ruc,
                        'razon_social' => $creditNote->company->razon_social,
                        'nombre_comercial' => $creditNote->company->nombre_comercial,
                        'direccion' => $creditNote->company->direccion,
                        'ubigeo' => $creditNote->company->ubigeo,
                        'telefono' => $creditNote->company->telefono,
                        'email' => $creditNote->company->email,
                        'logo_path' => $creditNote->company->logo_path
                    ],

                    // Sucursal (datos esenciales)
                    'sucursal' => [
                        'codigo' => $creditNote->branch->codigo,
                        'nombre' => $creditNote->branch->nombre,
                        'direccion' => $creditNote->branch->direccion,
                        'ubigeo' => $creditNote->branch->ubigeo,
                        'distrito' => $creditNote->branch->distrito,
                        'provincia' => $creditNote->branch->provincia,
                        'departamento' => $creditNote->branch->departamento
                    ],

                    // Cliente (datos esenciales)
                    'cliente' => [
                        'tipo_documento' => $creditNote->client->tipo_documento,
                        'numero_documento' => $creditNote->client->numero_documento,
                        'razon_social' => $creditNote->client->razon_social,
                        'nombre_comercial' => $creditNote->client->nombre_comercial ?? null,
                        'direccion' => $creditNote->client->direccion,
                        'email' => $creditNote->client->email ?? null,
                        'telefono' => $creditNote->client->telefono ?? null
                    ],

                    // Documento afectado
                    'documento_afectado' => [
                        'tipo' => $creditNote->tipo_doc_afectado,
                        'serie' => $creditNote->serie_doc_afectado,
                        'numero' => $creditNote->num_doc_afectado,
                        'numero_completo' => $creditNote->serie_doc_afectado . '-' . $creditNote->num_doc_afectado
                    ],

                    // Totales
                    'totales' => [
                        'gravada' => (float) $creditNote->mto_oper_gravadas,
                        'exonerada' => (float) $creditNote->mto_oper_exoneradas,
                        'inafecta' => (float) $creditNote->mto_oper_inafectas,
                        'igv' => (float) $creditNote->mto_igv,
                        'isc' => (float) $creditNote->mto_isc,
                        'icbper' => (float) $creditNote->mto_icbper,
                        'total_impuestos' => (float) $creditNote->total_impuestos,
                        'total' => (float) $creditNote->mto_imp_venta
                    ],

                    // Detalles del documento
                    'detalles' => $creditNote->detalles,
                    'leyendas' => $creditNote->leyendas,

                    // Estado SUNAT
                    'estado_sunat' => $creditNote->estado_sunat,
                    'sunat' => [
                        'codigo' => $respuestaSunat['code'] ?? null,
                        'descripcion' => $respuestaSunat['description'] ?? null,
                        'notas' => $respuestaSunat['notes'] ?? []
                    ],

                    // Archivos
                    'archivos' => [
                        'xml' => $creditNote->xml_path,
                        'cdr' => $creditNote->cdr_path,
                        'pdf' => $creditNote->pdf_path,
                        'hash' => $creditNote->codigo_hash
                    ],

                    // Metadatos
                    'usuario_creacion' => $creditNote->usuario_creacion,
                    'created_at' => $creditNote->created_at,
                    'updated_at' => $creditNote->updated_at
                ],
                'message' => 'Nota de crédito obtenida correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nota de crédito no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function sendToSunat($id): JsonResponse
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);

            if ($creditNote->estado_sunat === 'ACEPTADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La nota de crédito ya fue enviada y aceptada por SUNAT'
                ], 400);
            }

            $result = $this->documentService->sendToSunat($creditNote, 'credit_note');

            if ($result['success']) {
                $doc = $result['document'];

                // Disparar webhook de aceptación
                try {
                    $webhookService = app(\App\Services\WebhookService::class);
                    $webhookService->trigger($creditNote->company_id, 'credit_note.accepted', [
                        'document_id' => $doc->id,
                        'numero' => $doc->numero_completo,
                        'serie' => $doc->serie,
                        'correlativo' => $doc->correlativo,
                        'fecha_emision' => $doc->fecha_emision,
                        'monto' => (float) $doc->mto_imp_venta,
                        'moneda' => $doc->moneda,
                        'estado_sunat' => $doc->estado_sunat,
                        'documento_afectado' => $doc->serie_doc_afectado . '-' . $doc->num_doc_afectado,
                        'cliente' => [
                            'razon_social' => $doc->client->razon_social ?? null,
                            'num_doc' => $doc->client->numero_documento ?? null,
                        ]
                    ]);
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    Log::warning('Error al disparar webhook', [
                        'credit_note_id' => $doc->id,
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
                        'tipo_nota' => $doc->tipo_nota,

                        'documento_afectado' => [
                            'tipo' => $doc->tipo_doc_afectado,
                            'numero' => $doc->serie_doc_afectado . '-' . $doc->num_doc_afectado
                        ],

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
                    'message' => 'Nota de crédito enviada correctamente a SUNAT'
                ]);
            } else {
                // Manejar diferentes tipos de error
                $errorCode = 'UNKNOWN';
                $errorMessage = 'Error desconocido';

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
                    $webhookService->trigger($creditNote->company_id, 'credit_note.rejected', [
                        'document_id' => $creditNote->id,
                        'numero' => $creditNote->numero_completo,
                        'serie' => $creditNote->serie,
                        'correlativo' => $creditNote->correlativo,
                        'fecha_emision' => $creditNote->fecha_emision,
                        'monto' => (float) $creditNote->mto_imp_venta,
                        'moneda' => $creditNote->moneda,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'documento_afectado' => $creditNote->serie_doc_afectado . '-' . $creditNote->num_doc_afectado,
                        'cliente' => [
                            'razon_social' => $creditNote->client->razon_social ?? null,
                            'num_doc' => $creditNote->client->numero_documento ?? null,
                        ]
                    ]);
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    Log::warning('Error al disparar webhook de rechazo', [
                        'credit_note_id' => $creditNote->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'data' => $result['document'],
                    'message' => 'Error al enviar a SUNAT: ' . $errorMessage,
                    'error_code' => $errorCode
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el envío a SUNAT',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadXml($id)
    {
        try {
            $creditNote = CreditNote::findOrFail($id);
            
            $download = $this->fileService->downloadXml($creditNote);
            
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
            $creditNote = CreditNote::findOrFail($id);
            
            $download = $this->fileService->downloadCdr($creditNote);
            
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

    public function downloadPdf(Request $request, $id)
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);

            return $this->downloadDocumentPdf($creditNote, $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generatePdf(Request $request, $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);

            return $this->generateDocumentPdf($creditNote, 'credit-note', $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMotivos(): JsonResponse
    {
        $motivos = [
            ['code' => '01', 'name' => 'Anulación de la operación'],
            ['code' => '02', 'name' => 'Anulación por error en el RUC'],
            ['code' => '03', 'name' => 'Corrección por error en la descripción'],
            ['code' => '04', 'name' => 'Descuento global'],
            ['code' => '05', 'name' => 'Descuento por ítem'],
            ['code' => '06', 'name' => 'Devolución total'],
            ['code' => '07', 'name' => 'Devolución por ítem'],
            ['code' => '08', 'name' => 'Bonificación'],
            ['code' => '09', 'name' => 'Disminución en el valor'],
            ['code' => '10', 'name' => 'Otros conceptos'],
            ['code' => '11', 'name' => 'Ajustes de operaciones de exportación'],
            ['code' => '12', 'name' => 'Ajustes afectos al IVAP'],
            ['code' => '13', 'name' => 'Ajustes - montos y/o fechas de pago'],
        ];

        return response()->json([
            'success' => true,
            'data' => $motivos,
            'message' => 'Motivos de nota de crédito obtenidos correctamente'
        ]);
    }
}