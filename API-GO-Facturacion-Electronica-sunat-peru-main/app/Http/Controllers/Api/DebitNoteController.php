<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Services\DocumentService;
use App\Services\FileService;
use App\Models\DebitNote;
use App\Http\Requests\IndexDebitNoteRequest;
use App\Http\Requests\StoreDebitNoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DebitNoteController extends Controller
{
    use HandlesPdfGeneration;

    protected $documentService;
    protected $fileService;

    public function __construct(DocumentService $documentService, FileService $fileService)
    {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
    }

    public function index(IndexDebitNoteRequest $request): JsonResponse
    {
        try {
            $query = DebitNote::with(['company', 'branch', 'client']);

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
            $debitNotes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transformar datos para respuesta optimizada
            $data = collect($debitNotes->items())->map(function ($debitNote) {
                return [
                    'id' => $debitNote->id,
                    'numero_completo' => $debitNote->numero_completo,
                    'serie' => $debitNote->serie,
                    'correlativo' => $debitNote->correlativo,
                    'fecha_emision' => $debitNote->fecha_emision,
                    'moneda' => $debitNote->moneda,
                    'estado_sunat' => $debitNote->estado_sunat,
                    'tipo_nota' => $debitNote->tipo_nota,

                    'cliente' => [
                        'tipo_documento' => $debitNote->client->tipo_documento ?? null,
                        'numero_documento' => $debitNote->client->numero_documento ?? null,
                        'razon_social' => $debitNote->client->razon_social ?? null
                    ],

                    'documento_afectado' => [
                        'tipo' => $debitNote->tipo_doc_afectado,
                        'numero' => $debitNote->serie_doc_afectado . '-' . $debitNote->num_doc_afectado
                    ],

                    'totales' => [
                        'gravada' => (float) $debitNote->mto_oper_gravadas,
                        'igv' => (float) $debitNote->mto_igv,
                        'total' => (float) $debitNote->mto_imp_venta
                    ],

                    'archivos' => [
                        'xml_existe' => !empty($debitNote->xml_path),
                        'cdr_existe' => !empty($debitNote->cdr_path)
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $debitNotes->currentPage(),
                    'last_page' => $debitNotes->lastPage(),
                    'per_page' => $debitNotes->perPage(),
                    'total' => $debitNotes->total()
                ],
                'message' => 'Notas de débito obtenidas correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notas de débito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreDebitNoteRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Crear la nota de débito
            $debitNote = $this->documentService->createDebitNote($validated);

            // Respuesta simplificada
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $debitNote->id,
                    'numero_completo' => $debitNote->numero_completo,
                    'serie' => $debitNote->serie,
                    'correlativo' => $debitNote->correlativo,
                    'fecha_emision' => $debitNote->fecha_emision,
                    'moneda' => $debitNote->moneda,
                    'estado_sunat' => $debitNote->estado_sunat,
                    'tipo_nota' => $debitNote->tipo_nota,

                    'empresa' => [
                        'ruc' => $debitNote->company->ruc,
                        'razon_social' => $debitNote->company->razon_social
                    ],
                    'sucursal' => [
                        'codigo' => $debitNote->branch->codigo,
                        'nombre' => $debitNote->branch->nombre
                    ],
                    'cliente' => [
                        'tipo_documento' => $debitNote->client->tipo_documento,
                        'numero_documento' => $debitNote->client->numero_documento,
                        'razon_social' => $debitNote->client->razon_social
                    ],

                    'documento_afectado' => [
                        'tipo' => $debitNote->tipo_doc_afectado,
                        'serie' => $debitNote->serie_doc_afectado,
                        'numero' => $debitNote->num_doc_afectado
                    ],

                    'totales' => [
                        'gravada' => (float) $debitNote->mto_oper_gravadas,
                        'igv' => (float) $debitNote->mto_igv,
                        'total' => (float) $debitNote->mto_imp_venta
                    ],

                    'detalles' => $debitNote->detalles,
                    'leyendas' => $debitNote->leyendas
                ],
                'message' => 'Nota de débito creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la nota de débito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $debitNote = DebitNote::with(['company', 'branch', 'client'])->findOrFail($id);

            // Parsear respuesta SUNAT si existe
            $respuestaSunat = null;
            if ($debitNote->respuesta_sunat) {
                $respuestaSunat = json_decode($debitNote->respuesta_sunat, true);
            }

            // Respuesta optimizada con información completa
            return response()->json([
                'success' => true,
                'data' => [
                    // Información principal
                    'id' => $debitNote->id,
                    'numero_completo' => $debitNote->numero_completo,
                    'serie' => $debitNote->serie,
                    'correlativo' => $debitNote->correlativo,
                    'tipo_documento' => $debitNote->tipo_documento,
                    'fecha_emision' => $debitNote->fecha_emision,
                    'moneda' => $debitNote->moneda,
                    'tipo_nota' => $debitNote->tipo_nota,

                    // Empresa (datos esenciales)
                    'empresa' => [
                        'ruc' => $debitNote->company->ruc,
                        'razon_social' => $debitNote->company->razon_social,
                        'nombre_comercial' => $debitNote->company->nombre_comercial,
                        'direccion' => $debitNote->company->direccion,
                        'ubigeo' => $debitNote->company->ubigeo,
                        'telefono' => $debitNote->company->telefono,
                        'email' => $debitNote->company->email,
                        'logo_path' => $debitNote->company->logo_path
                    ],

                    // Sucursal (datos esenciales)
                    'sucursal' => [
                        'codigo' => $debitNote->branch->codigo,
                        'nombre' => $debitNote->branch->nombre,
                        'direccion' => $debitNote->branch->direccion,
                        'ubigeo' => $debitNote->branch->ubigeo,
                        'distrito' => $debitNote->branch->distrito,
                        'provincia' => $debitNote->branch->provincia,
                        'departamento' => $debitNote->branch->departamento
                    ],

                    // Cliente (datos esenciales)
                    'cliente' => [
                        'tipo_documento' => $debitNote->client->tipo_documento,
                        'numero_documento' => $debitNote->client->numero_documento,
                        'razon_social' => $debitNote->client->razon_social,
                        'nombre_comercial' => $debitNote->client->nombre_comercial ?? null,
                        'direccion' => $debitNote->client->direccion,
                        'email' => $debitNote->client->email ?? null,
                        'telefono' => $debitNote->client->telefono ?? null
                    ],

                    // Documento afectado
                    'documento_afectado' => [
                        'tipo' => $debitNote->tipo_doc_afectado,
                        'serie' => $debitNote->serie_doc_afectado,
                        'numero' => $debitNote->num_doc_afectado,
                        'numero_completo' => $debitNote->serie_doc_afectado . '-' . $debitNote->num_doc_afectado
                    ],

                    // Totales
                    'totales' => [
                        'gravada' => (float) $debitNote->mto_oper_gravadas,
                        'exonerada' => (float) $debitNote->mto_oper_exoneradas,
                        'inafecta' => (float) $debitNote->mto_oper_inafectas,
                        'igv' => (float) $debitNote->mto_igv,
                        'isc' => (float) $debitNote->mto_isc,
                        'icbper' => (float) $debitNote->mto_icbper,
                        'total_impuestos' => (float) $debitNote->total_impuestos,
                        'total' => (float) $debitNote->mto_imp_venta
                    ],

                    // Detalles del documento
                    'detalles' => $debitNote->detalles,
                    'leyendas' => $debitNote->leyendas,

                    // Estado SUNAT
                    'estado_sunat' => $debitNote->estado_sunat,
                    'sunat' => [
                        'codigo' => $respuestaSunat['code'] ?? null,
                        'descripcion' => $respuestaSunat['description'] ?? null,
                        'notas' => $respuestaSunat['notes'] ?? []
                    ],

                    // Archivos
                    'archivos' => [
                        'xml' => $debitNote->xml_path,
                        'cdr' => $debitNote->cdr_path,
                        'pdf' => $debitNote->pdf_path,
                        'hash' => $debitNote->codigo_hash
                    ],

                    // Metadatos
                    'usuario_creacion' => $debitNote->usuario_creacion,
                    'created_at' => $debitNote->created_at,
                    'updated_at' => $debitNote->updated_at
                ],
                'message' => 'Nota de débito obtenida correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nota de débito no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function sendToSunat($id): JsonResponse
    {
        try {
            $debitNote = DebitNote::with(['company', 'branch', 'client'])->findOrFail($id);

            if ($debitNote->estado_sunat === 'ACEPTADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La nota de débito ya fue enviada y aceptada por SUNAT'
                ], 400);
            }

            $result = $this->documentService->sendToSunat($debitNote, 'debit_note');

            if ($result['success']) {
                $doc = $result['document'];

                // Disparar webhook de aceptación
                try {
                    $webhookService = app(\App\Services\WebhookService::class);
                    $webhookService->trigger($debitNote->company_id, 'debit_note.accepted', [
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
                        'debit_note_id' => $doc->id,
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
                    'message' => 'Nota de débito enviada correctamente a SUNAT'
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
                    $webhookService->trigger($debitNote->company_id, 'debit_note.rejected', [
                        'document_id' => $debitNote->id,
                        'numero' => $debitNote->numero_completo,
                        'serie' => $debitNote->serie,
                        'correlativo' => $debitNote->correlativo,
                        'fecha_emision' => $debitNote->fecha_emision,
                        'monto' => (float) $debitNote->mto_imp_venta,
                        'moneda' => $debitNote->moneda,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'documento_afectado' => $debitNote->serie_doc_afectado . '-' . $debitNote->num_doc_afectado,
                        'cliente' => [
                            'razon_social' => $debitNote->client->razon_social ?? null,
                            'num_doc' => $debitNote->client->numero_documento ?? null,
                        ]
                    ]);
                    $webhookService->processPendingDeliveries();
                } catch (\Exception $e) {
                    Log::warning('Error al disparar webhook de rechazo', [
                        'debit_note_id' => $debitNote->id,
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
            $debitNote = DebitNote::findOrFail($id);
            
            $download = $this->fileService->downloadXml($debitNote);
            
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
            $debitNote = DebitNote::findOrFail($id);
            
            $download = $this->fileService->downloadCdr($debitNote);
            
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
            $debitNote = DebitNote::with(['company', 'branch', 'client'])->findOrFail($id);

            return $this->downloadDocumentPdf($debitNote, $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMotivos(): JsonResponse
    {
        $motivos = [
            ['code' => '01', 'name' => 'Intereses por mora'],
            ['code' => '02', 'name' => 'Aumento en el valor'],
            ['code' => '03', 'name' => 'Penalidades/otros conceptos'],
            ['code' => '10', 'name' => 'Ajustes de operaciones de exportación'],
            ['code' => '11', 'name' => 'Ajustes afectos al IVAP'],
        ];

        return response()->json([
            'success' => true,
            'data' => $motivos,
            'message' => 'Motivos de nota de débito obtenidos correctamente'
        ]);
    }

    public function generatePdf(Request $request, $id): JsonResponse
    {
        try {
            $debitNote = DebitNote::with(['company', 'branch', 'client'])->findOrFail($id);

            return $this->generateDocumentPdf($debitNote, 'debit-note', $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}