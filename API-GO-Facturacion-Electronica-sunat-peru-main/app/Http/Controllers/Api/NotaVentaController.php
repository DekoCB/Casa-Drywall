<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Http\Requests\NotaVenta\StoreNotaVentaRequest;
use App\Services\DocumentService;
use App\Services\FileService;
use App\Models\NotaVenta;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotaVentaController extends Controller
{
    use HandlesPdfGeneration;

    protected $documentService;
    protected $fileService;

    public function __construct(DocumentService $documentService, FileService $fileService)
    {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
    }

    /**
     * Listar Notas de Venta con filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = NotaVenta::with(['company', 'branch', 'client']);

            // Filtros
            if ($request->has('company_id')) {
                $query->byCompany($request->company_id);
            }

            if ($request->has('branch_id')) {
                $query->byBranch($request->branch_id);
            }

            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->byDateRange($request->fecha_desde, $request->fecha_hasta);
            }

            if ($request->has('serie')) {
                $query->where('serie', $request->serie);
            }

            if ($request->has('numero_completo')) {
                $query->where('numero_completo', 'like', '%' . $request->numero_completo . '%');
            }

            // Ordenamiento
            $query->recent();

            // Paginación
            $perPage = $request->get('per_page', 15);
            $notasVenta = $query->paginate($perPage);

            // Optimizar respuesta para listado
            $data = $notasVenta->map(function ($notaVenta) {
                return [
                    'id' => $notaVenta->id,
                    'numero_completo' => $notaVenta->numero_completo,
                    'serie' => $notaVenta->serie,
                    'correlativo' => $notaVenta->correlativo,
                    'fecha_emision' => $notaVenta->fecha_emision,
                    'fecha_vencimiento' => $notaVenta->fecha_vencimiento,
                    'moneda' => $notaVenta->moneda,
                    'cliente' => [
                        'tipo_documento' => $notaVenta->client->tipo_documento ?? null,
                        'numero_documento' => $notaVenta->client->numero_documento ?? null,
                        'razon_social' => $notaVenta->client->razon_social ?? null,
                    ],
                    'totales' => [
                        'gravada' => (float) $notaVenta->mto_oper_gravadas,
                        'igv' => (float) $notaVenta->mto_igv,
                        'total' => (float) $notaVenta->mto_imp_venta,
                    ],
                    'archivos' => [
                        'pdf_existe' => !empty($notaVenta->ruta_pdf),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $data,
                    'current_page' => $notasVenta->currentPage(),
                    'per_page' => $notasVenta->perPage(),
                    'total' => $notasVenta->total(),
                    'last_page' => $notasVenta->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar notas de venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notas de venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear nueva Nota de Venta
     */
    public function store(StoreNotaVentaRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $notaVenta = $this->documentService->createNotaVenta($validated);

            // Cargar relaciones necesarias
            $notaVenta->load(['company', 'branch', 'client']);

            return response()->json([
                'success' => true,
                'message' => 'Nota de Venta creada exitosamente',
                'data' => [
                    'id' => $notaVenta->id,
                    'numero_completo' => $notaVenta->numero_completo,
                    'serie' => $notaVenta->serie,
                    'correlativo' => $notaVenta->correlativo,
                    'fecha_emision' => $notaVenta->fecha_emision,
                    'fecha_vencimiento' => $notaVenta->fecha_vencimiento,
                    'hora_emision' => $notaVenta->hora_emision,
                    'tipo_doc' => $notaVenta->tipo_doc,
                    'moneda' => $notaVenta->moneda,
                    'tipo_cambio' => (float) $notaVenta->tipo_cambio,
                    'empresa' => [
                        'ruc' => $notaVenta->company->ruc,
                        'razon_social' => $notaVenta->company->razon_social,
                    ],
                    'sucursal' => [
                        'codigo' => $notaVenta->branch->codigo,
                        'nombre' => $notaVenta->branch->nombre,
                    ],
                    'cliente' => [
                        'tipo_documento' => $notaVenta->client->tipo_documento,
                        'numero_documento' => $notaVenta->client->numero_documento,
                        'razon_social' => $notaVenta->client->razon_social,
                    ],
                    'totales' => [
                        'gravada' => (float) $notaVenta->mto_oper_gravadas,
                        'inafecta' => (float) $notaVenta->mto_oper_inafectas,
                        'exonerada' => (float) $notaVenta->mto_oper_exoneradas,
                        'gratuita' => (float) $notaVenta->mto_oper_gratuitas,
                        'igv' => (float) $notaVenta->mto_igv,
                        'total_impuestos' => (float) $notaVenta->mto_total_impuestos,
                        'total' => (float) $notaVenta->mto_imp_venta,
                    ],
                    'detalles' => $notaVenta->detalles ?? [],
                    'leyendas' => $notaVenta->leyendas ?? [],
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear nota de venta', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la nota de venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalle de una Nota de Venta
     */
    public function show(int $id): JsonResponse
    {
        try {
            $notaVenta = NotaVenta::with(['company', 'branch', 'client'])
                                  ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $notaVenta->id,
                    'numero_completo' => $notaVenta->numero_completo,
                    'serie' => $notaVenta->serie,
                    'correlativo' => $notaVenta->correlativo,
                    'fecha_emision' => $notaVenta->fecha_emision,
                    'fecha_vencimiento' => $notaVenta->fecha_vencimiento,
                    'hora_emision' => $notaVenta->hora_emision,
                    'tipo_doc' => $notaVenta->tipo_doc,
                    'moneda' => $notaVenta->moneda,
                    'tipo_cambio' => (float) $notaVenta->tipo_cambio,
                    'observaciones' => $notaVenta->observaciones,
                    'empresa' => [
                        'ruc' => $notaVenta->company->ruc,
                        'razon_social' => $notaVenta->company->razon_social,
                        'nombre_comercial' => $notaVenta->company->nombre_comercial,
                        'direccion' => $notaVenta->company->direccion,
                        'ubigeo' => $notaVenta->company->ubigeo,
                        'departamento' => $notaVenta->company->departamento,
                        'provincia' => $notaVenta->company->provincia,
                        'distrito' => $notaVenta->company->distrito,
                    ],
                    'sucursal' => [
                        'codigo' => $notaVenta->branch->codigo,
                        'nombre' => $notaVenta->branch->nombre,
                        'direccion' => $notaVenta->branch->direccion,
                        'ubigeo' => $notaVenta->branch->ubigeo,
                        'departamento' => $notaVenta->branch->departamento,
                        'provincia' => $notaVenta->branch->provincia,
                        'distrito' => $notaVenta->branch->distrito,
                    ],
                    'cliente' => [
                        'tipo_documento' => $notaVenta->client->tipo_documento,
                        'numero_documento' => $notaVenta->client->numero_documento,
                        'razon_social' => $notaVenta->client->razon_social,
                        'direccion' => $notaVenta->client->direccion,
                        'email' => $notaVenta->client->email,
                        'telefono' => $notaVenta->client->telefono,
                    ],
                    'totales' => [
                        'gravada' => (float) $notaVenta->mto_oper_gravadas,
                        'inafecta' => (float) $notaVenta->mto_oper_inafectas,
                        'exonerada' => (float) $notaVenta->mto_oper_exoneradas,
                        'gratuita' => (float) $notaVenta->mto_oper_gratuitas,
                        'exportacion' => (float) $notaVenta->mto_oper_exportacion,
                        'igv' => (float) $notaVenta->mto_igv,
                        'isc' => (float) $notaVenta->mto_isc,
                        'icbper' => (float) $notaVenta->mto_icbper,
                        'otros_cargos' => (float) $notaVenta->mto_otros_cargos,
                        'total_impuestos' => (float) $notaVenta->mto_total_impuestos,
                        'valor_venta' => (float) $notaVenta->mto_valor_venta,
                        'precio_venta' => (float) $notaVenta->mto_precio_venta,
                        'descuentos' => (float) $notaVenta->mto_descuentos,
                        'total' => (float) $notaVenta->mto_imp_venta,
                        'redondeo' => (float) $notaVenta->mto_redondeo,
                    ],
                    'detalles' => $notaVenta->detalles ?? [],
                    'leyendas' => $notaVenta->leyendas ?? [],
                    'archivos' => [
                        'pdf' => $notaVenta->ruta_pdf,
                        'pdf_existe' => !empty($notaVenta->ruta_pdf),
                    ],
                    'created_at' => $notaVenta->created_at,
                    'updated_at' => $notaVenta->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nota de Venta no encontrada',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Actualizar Nota de Venta
     */
    public function update(StoreNotaVentaRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $notaVenta = $this->documentService->updateNotaVenta($id, $validated);

            // Cargar relaciones necesarias
            $notaVenta->load(['company', 'branch', 'client']);

            return response()->json([
                'success' => true,
                'message' => 'Nota de Venta actualizada exitosamente',
                'data' => [
                    'id' => $notaVenta->id,
                    'numero_completo' => $notaVenta->numero_completo,
                    'serie' => $notaVenta->serie,
                    'correlativo' => $notaVenta->correlativo,
                    'fecha_emision' => $notaVenta->fecha_emision,
                    'fecha_vencimiento' => $notaVenta->fecha_vencimiento,
                    'hora_emision' => $notaVenta->hora_emision,
                    'tipo_doc' => $notaVenta->tipo_doc,
                    'moneda' => $notaVenta->moneda,
                    'tipo_cambio' => (float) $notaVenta->tipo_cambio,
                    'empresa' => [
                        'ruc' => $notaVenta->company->ruc,
                        'razon_social' => $notaVenta->company->razon_social,
                    ],
                    'sucursal' => [
                        'codigo' => $notaVenta->branch->codigo,
                        'nombre' => $notaVenta->branch->nombre,
                    ],
                    'cliente' => [
                        'tipo_documento' => $notaVenta->client->tipo_documento,
                        'numero_documento' => $notaVenta->client->numero_documento,
                        'razon_social' => $notaVenta->client->razon_social,
                    ],
                    'totales' => [
                        'gravada' => (float) $notaVenta->mto_oper_gravadas,
                        'inafecta' => (float) $notaVenta->mto_oper_inafectas,
                        'exonerada' => (float) $notaVenta->mto_oper_exoneradas,
                        'gratuita' => (float) $notaVenta->mto_oper_gratuitas,
                        'igv' => (float) $notaVenta->mto_igv,
                        'total_impuestos' => (float) $notaVenta->mto_total_impuestos,
                        'total' => (float) $notaVenta->mto_imp_venta,
                    ],
                    'detalles' => $notaVenta->detalles ?? [],
                    'leyendas' => $notaVenta->leyendas ?? [],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar nota de venta', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la nota de venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar Nota de Venta (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $notaVenta = NotaVenta::findOrFail($id);
            $notaVenta->delete();

            return response()->json([
                'success' => true,
                'message' => 'Nota de Venta eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la nota de venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar PDF
     */
    public function generatePdf(int $id, Request $request): JsonResponse
    {
        try {
            $notaVenta = NotaVenta::with(['company', 'branch', 'client'])
                                  ->findOrFail($id);

            return $this->generateDocumentPdf($notaVenta, 'nota-venta', $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar PDF
     */
    public function downloadPdf(int $id, Request $request)
    {
        try {
            $notaVenta = NotaVenta::with(['company', 'branch', 'client'])
                                  ->findOrFail($id);

            return $this->downloadDocumentPdf($notaVenta, $request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
