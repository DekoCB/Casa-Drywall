<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Correlative;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class CorrelativeController extends Controller
{
    /**
     * Tipos de documentos SUNAT válidos
     */
    private const TIPOS_DOCUMENTO = [
        '01' => 'Factura',
        '03' => 'Boleta de Venta',
        '07' => 'Nota de Crédito',
        '08' => 'Nota de Débito',
        '09' => 'Guía de Remisión',
        '17' => 'Nota de Venta',
        '20' => 'Comprobante de Retención',
        'RC' => 'Resumen de Anulaciones',
        'RA' => 'Resumen Diario'
    ];

    /**
     * Obtener series como array desde el campo (ya manejado por Laravel cast)
     * Retorna array vacío si el campo es null
     */
    private function getSeries($seriesField): array
    {
        if (empty($seriesField) || !is_array($seriesField)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $seriesField)));
    }

    /**
     * Preparar array de series para guardar (Laravel lo convertirá a JSON automáticamente)
     * Limpia duplicados y valores vacíos
     */
    private function prepareSeriesArray(array $series): ?array
    {
        // Limpiar comillas y espacios de cada elemento
        $series = array_map(function($item) {
            return trim(str_replace(['"', "'"], '', $item));
        }, $series);

        // Filtrar elementos vacíos y duplicados, reindexar
        $series = array_values(array_filter(array_unique($series)));

        return !empty($series) ? $series : null;
    }

    /**
     * Listar correlativos de una sucursal
     */
    public function index(Branch $branch): JsonResponse
    {
        try {
            $correlatives = $branch->correlatives()
                                  ->orderBy('tipo_documento')
                                  ->orderBy('serie')
                                  ->get()
                                  ->map(function ($correlative) {
                                      return [
                                          'id' => $correlative->id,
                                          'branch_id' => $correlative->branch_id,
                                          'tipo_documento' => $correlative->tipo_documento,
                                          'tipo_documento_nombre' => self::TIPOS_DOCUMENTO[$correlative->tipo_documento] ?? 'Desconocido',
                                          'serie' => $correlative->serie,
                                          'correlativo_actual' => $correlative->correlativo_actual,
                                          'numero_completo' => $correlative->numero_completo,
                                          'proximo_numero' => $correlative->serie . '-' . str_pad($correlative->correlativo_actual + 1, 6, '0', STR_PAD_LEFT),
                                          'created_at' => $correlative->created_at,
                                          'updated_at' => $correlative->updated_at
                                      ];
                                  });

            return response()->json([
                'success' => true,
                'data' => [
                    'branch' => [
                        'id' => $branch->id,
                        'codigo' => $branch->codigo,
                        'nombre' => $branch->nombre,
                        'company_id' => $branch->company_id
                    ],
                    'correlatives' => $correlatives
                ],
                'meta' => [
                    'total' => $correlatives->count(),
                    'tipos_disponibles' => self::TIPOS_DOCUMENTO
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al listar correlativos", [
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener correlativos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo correlativo para una sucursal
     */
    public function store(Request $request, Branch $branch): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo_documento' => 'required|string|max:2|in:' . implode(',', array_keys(self::TIPOS_DOCUMENTO)),
                'serie' => 'required|string|max:4|regex:/^[A-Z0-9]+$/',
                'correlativo_inicial' => 'integer|min:0|max:99999999'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Limpiar la serie de comillas y espacios
            $serieLimpia = trim(str_replace(['"', "'"], '', $request->serie));
            $serieUpper = strtoupper($serieLimpia);

            // Verificar que no exista la combinación sucursal + tipo + serie
            $existingCorrelative = Correlative::where('branch_id', $branch->id)
                                             ->where('tipo_documento', $request->tipo_documento)
                                             ->where('serie', $serieUpper)
                                             ->first();

            if ($existingCorrelative) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un correlativo para esta sucursal con el mismo tipo de documento y serie'
                ], 400);
            }

            $correlative = Correlative::create([
                'branch_id' => $branch->id,
                'tipo_documento' => $request->tipo_documento,
                'serie' => $serieUpper,
                'correlativo_actual' => $request->correlativo_inicial ?? 0
            ]);

            // Actualizar el campo de series en la tabla branches
            switch ($request->tipo_documento) {
                case '01': // Factura
                    $series = $this->getSeries($branch->series_factura);
                    if (!in_array($serieUpper, $series)) {
                        $series[] = $serieUpper;
                        $branch->series_factura = $this->prepareSeriesArray($series);
                    }
                    break;
                case '03': // Boleta
                    $series = $this->getSeries($branch->series_boleta);
                    if (!in_array($serieUpper, $series)) {
                        $series[] = $serieUpper;
                        $branch->series_boleta = $this->prepareSeriesArray($series);
                    }
                    break;
                case '07': // Nota de Crédito
                    $series = $this->getSeries($branch->series_nota_credito);
                    if (!in_array($serieUpper, $series)) {
                        $series[] = $serieUpper;
                        $branch->series_nota_credito = $this->prepareSeriesArray($series);
                    }
                    break;
                case '08': // Nota de Débito
                    $series = $this->getSeries($branch->series_nota_debito);
                    if (!in_array($serieUpper, $series)) {
                        $series[] = $serieUpper;
                        $branch->series_nota_debito = $this->prepareSeriesArray($series);
                    }
                    break;
                case '09': // Guía de Remisión
                    $series = $this->getSeries($branch->series_guia_remision);
                    if (!in_array($serieUpper, $series)) {
                        $series[] = $serieUpper;
                        $branch->series_guia_remision = $this->prepareSeriesArray($series);
                    }
                    break;
            }
            $branch->save();

            Log::info("Correlativo creado exitosamente y serie actualizada", [
                'correlative_id' => $correlative->id,
                'branch_id' => $branch->id,
                'tipo_documento' => $correlative->tipo_documento,
                'serie' => $correlative->serie
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correlativo creado exitosamente',
                'data' => [
                    'id' => $correlative->id,
                    'branch_id' => $correlative->branch_id,
                    'tipo_documento' => $correlative->tipo_documento,
                    'tipo_documento_nombre' => self::TIPOS_DOCUMENTO[$correlative->tipo_documento],
                    'serie' => $correlative->serie,
                    'correlativo_actual' => $correlative->correlativo_actual,
                    'numero_completo' => $correlative->numero_completo,
                    'proximo_numero' => $correlative->serie . '-' . str_pad($correlative->correlativo_actual + 1, 6, '0', STR_PAD_LEFT)
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error("Error al crear correlativo", [
                'branch_id' => $branch->id,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear correlativo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar correlativo
     */
    public function update(Request $request, Branch $branch, Correlative $correlative): JsonResponse
    {
        try {
            // Verificar que el correlativo pertenece a la sucursal
            if ($correlative->branch_id !== $branch->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correlativo no pertenece a esta sucursal'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'tipo_documento' => 'required|string|max:2|in:' . implode(',', array_keys(self::TIPOS_DOCUMENTO)),
                'serie' => 'required|string|max:4|regex:/^[A-Z0-9]+$/',
                'correlativo_actual' => 'required|integer|min:0|max:99999999'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Limpiar la serie de comillas y espacios
            $serieLimpia = trim(str_replace(['"', "'"], '', $request->serie));
            $serieUpper = strtoupper($serieLimpia);

            // Verificar que no exista otra combinación igual (excluyendo el actual)
            $existingCorrelative = Correlative::where('branch_id', $branch->id)
                                             ->where('tipo_documento', $request->tipo_documento)
                                             ->where('serie', $serieUpper)
                                             ->where('id', '!=', $correlative->id)
                                             ->first();

            if ($existingCorrelative) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro correlativo para esta sucursal con el mismo tipo de documento y serie'
                ], 400);
            }

            $correlative->update([
                'tipo_documento' => $request->tipo_documento,
                'serie' => $serieUpper,
                'correlativo_actual' => $request->correlativo_actual
            ]);

            Log::info("Correlativo actualizado exitosamente", [
                'correlative_id' => $correlative->id,
                'branch_id' => $branch->id,
                'changes' => $correlative->getChanges()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correlativo actualizado exitosamente',
                'data' => [
                    'id' => $correlative->id,
                    'branch_id' => $correlative->branch_id,
                    'tipo_documento' => $correlative->tipo_documento,
                    'tipo_documento_nombre' => self::TIPOS_DOCUMENTO[$correlative->tipo_documento],
                    'serie' => $correlative->serie,
                    'correlativo_actual' => $correlative->correlativo_actual,
                    'numero_completo' => $correlative->numero_completo,
                    'proximo_numero' => $correlative->serie . '-' . str_pad($correlative->correlativo_actual + 1, 6, '0', STR_PAD_LEFT)
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar correlativo", [
                'correlative_id' => $correlative->id,
                'branch_id' => $branch->id,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar correlativo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar correlativo
     */
    public function destroy(Branch $branch, Correlative $correlative): JsonResponse
    {
        try {
            // Verificar que el correlativo pertenece a la sucursal
            if ($correlative->branch_id !== $branch->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correlativo no pertenece a esta sucursal'
                ], 404);
            }

            // Verificar si hay documentos usando esta serie
            // Aquí podrías agregar validaciones adicionales si es necesario

            $correlativeInfo = [
                'id' => $correlative->id,
                'tipo_documento' => $correlative->tipo_documento,
                'serie' => $correlative->serie,
                'correlativo_actual' => $correlative->correlativo_actual
            ];

            // Eliminar la serie del campo en branches
            switch ($correlative->tipo_documento) {
                case '01': // Factura
                    $series = $this->getSeries($branch->series_factura);
                    $series = array_values(array_diff($series, [$correlative->serie]));
                    $branch->series_factura = $this->prepareSeriesArray($series);
                    break;
                case '03': // Boleta
                    $series = $this->getSeries($branch->series_boleta);
                    $series = array_values(array_diff($series, [$correlative->serie]));
                    $branch->series_boleta = $this->prepareSeriesArray($series);
                    break;
                case '07': // Nota de Crédito
                    $series = $this->getSeries($branch->series_nota_credito);
                    $series = array_values(array_diff($series, [$correlative->serie]));
                    $branch->series_nota_credito = $this->prepareSeriesArray($series);
                    break;
                case '08': // Nota de Débito
                    $series = $this->getSeries($branch->series_nota_debito);
                    $series = array_values(array_diff($series, [$correlative->serie]));
                    $branch->series_nota_debito = $this->prepareSeriesArray($series);
                    break;
                case '09': // Guía de Remisión
                    $series = $this->getSeries($branch->series_guia_remision);
                    $series = array_values(array_diff($series, [$correlative->serie]));
                    $branch->series_guia_remision = $this->prepareSeriesArray($series);
                    break;
            }
            $branch->save();

            $correlative->delete();

            Log::warning("Correlativo eliminado y serie removida de branch", [
                'branch_id' => $branch->id,
                'correlative_info' => $correlativeInfo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correlativo eliminado exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error("Error al eliminar correlativo", [
                'correlative_id' => $correlative->id,
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar correlativo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear correlativos por lote para una sucursal
     */
    public function createBatch(Request $request, Branch $branch): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'correlativos' => 'required|array|min:1',
                'correlativos.*.tipo_documento' => 'required|string|max:2|in:' . implode(',', array_keys(self::TIPOS_DOCUMENTO)),
                'correlativos.*.serie' => 'required|string|max:4|regex:/^[A-Z0-9]+$/',
                'correlativos.*.correlativo_inicial' => 'integer|min:0|max:99999999',
                'correlativos.*.correlativo_actual' => 'integer|min:0|max:99999999'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $created = [];
            $errors = [];

            // Arrays para almacenar las series por tipo de documento
            $seriesFactura = $this->getSeries($branch->series_factura);
            $seriesBoleta = $this->getSeries($branch->series_boleta);
            $seriesNotaCredito = $this->getSeries($branch->series_nota_credito);
            $seriesNotaDebito = $this->getSeries($branch->series_nota_debito);
            $seriesGuiaRemision = $this->getSeries($branch->series_guia_remision);

            foreach ($request->correlativos as $index => $data) {
                try {
                    // Limpiar la serie de comillas y espacios
                    $serieLimpia = trim(str_replace(['"', "'"], '', $data['serie']));
                    $serieUpper = strtoupper($serieLimpia);

                    // Verificar que no exista la combinación
                    $exists = Correlative::where('branch_id', $branch->id)
                                        ->where('tipo_documento', $data['tipo_documento'])
                                        ->where('serie', $serieUpper)
                                        ->exists();

                    if ($exists) {
                        $errors[] = [
                            'index' => $index,
                            'error' => "Ya existe correlativo para tipo {$data['tipo_documento']} serie {$data['serie']}"
                        ];
                        continue;
                    }

                    // Usar correlativo_actual si existe, si no correlativo_inicial, si no 0
                    $correlativoInicial = $data['correlativo_actual'] ?? $data['correlativo_inicial'] ?? 0;

                    $correlative = Correlative::create([
                        'branch_id' => $branch->id,
                        'tipo_documento' => $data['tipo_documento'],
                        'serie' => $serieUpper,
                        'correlativo_actual' => $correlativoInicial
                    ]);

                    $created[] = [
                        'id' => $correlative->id,
                        'tipo_documento' => $correlative->tipo_documento,
                        'tipo_documento_nombre' => self::TIPOS_DOCUMENTO[$correlative->tipo_documento],
                        'serie' => $correlative->serie,
                        'correlativo_actual' => $correlative->correlativo_actual,
                        'numero_completo' => $correlative->numero_completo
                    ];

                    // Agregar la serie al array correspondiente según el tipo de documento
                    switch ($data['tipo_documento']) {
                        case '01': // Factura
                            if (!in_array($serieUpper, $seriesFactura)) {
                                $seriesFactura[] = $serieUpper;
                            }
                            break;
                        case '03': // Boleta
                            if (!in_array($serieUpper, $seriesBoleta)) {
                                $seriesBoleta[] = $serieUpper;
                            }
                            break;
                        case '07': // Nota de Crédito
                            if (!in_array($serieUpper, $seriesNotaCredito)) {
                                $seriesNotaCredito[] = $serieUpper;
                            }
                            break;
                        case '08': // Nota de Débito
                            if (!in_array($serieUpper, $seriesNotaDebito)) {
                                $seriesNotaDebito[] = $serieUpper;
                            }
                            break;
                        case '09': // Guía de Remisión
                            if (!in_array($serieUpper, $seriesGuiaRemision)) {
                                $seriesGuiaRemision[] = $serieUpper;
                            }
                            break;
                    }

                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Actualizar los campos de series en la tabla branches
            $branch->series_factura = $this->prepareSeriesArray($seriesFactura);
            $branch->series_boleta = $this->prepareSeriesArray($seriesBoleta);
            $branch->series_nota_credito = $this->prepareSeriesArray($seriesNotaCredito);
            $branch->series_nota_debito = $this->prepareSeriesArray($seriesNotaDebito);
            $branch->series_guia_remision = $this->prepareSeriesArray($seriesGuiaRemision);
            $branch->save();

            Log::info("Correlativos creados por lote y series actualizadas", [
                'branch_id' => $branch->id,
                'created_count' => count($created),
                'error_count' => count($errors),
                'series_factura' => $seriesFactura,
                'series_boleta' => $seriesBoleta,
                'series_nota_credito' => $seriesNotaCredito,
                'series_nota_debito' => $seriesNotaDebito,
                'series_guia_remision' => $seriesGuiaRemision
            ]);

            return response()->json([
                'success' => true,
                'message' => count($created) . ' correlativos creados exitosamente',
                'data' => [
                    'created' => $created,
                    'errors' => $errors,
                    'branch_series' => [
                        'series_factura' => $seriesFactura,
                        'series_boleta' => $seriesBoleta,
                        'series_nota_credito' => $seriesNotaCredito,
                        'series_nota_debito' => $seriesNotaDebito,
                        'series_guia_remision' => $seriesGuiaRemision
                    ]
                ],
                'meta' => [
                    'created_count' => count($created),
                    'error_count' => count($errors),
                    'total_requested' => count($request->correlativos)
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al crear correlativos por lote", [
                'branch_id' => $branch->id,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear correlativos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Incrementar correlativo (uso interno del sistema)
     */
    public function increment(Branch $branch, Correlative $correlative): JsonResponse
    {
        try {
            // Verificar que el correlativo pertenece a la sucursal
            if ($correlative->branch_id !== $branch->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correlativo no pertenece a esta sucursal'
                ], 404);
            }

            $oldCorrelativo = $correlative->correlativo_actual;
            $correlative->increment('correlativo_actual');

            Log::info("Correlativo incrementado", [
                'correlative_id' => $correlative->id,
                'branch_id' => $branch->id,
                'old_correlativo' => $oldCorrelativo,
                'new_correlativo' => $correlative->correlativo_actual
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correlativo incrementado exitosamente',
                'data' => [
                    'id' => $correlative->id,
                    'serie' => $correlative->serie,
                    'correlativo_anterior' => $oldCorrelativo,
                    'correlativo_actual' => $correlative->correlativo_actual,
                    'numero_usado' => $correlative->serie . '-' . str_pad($oldCorrelativo + 1, 6, '0', STR_PAD_LEFT),
                    'proximo_numero' => $correlative->serie . '-' . str_pad($correlative->correlativo_actual + 1, 6, '0', STR_PAD_LEFT)
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al incrementar correlativo", [
                'correlative_id' => $correlative->id,
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al incrementar correlativo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de documentos disponibles
     */
    public function getDocumentTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => collect(self::TIPOS_DOCUMENTO)->map(function ($nombre, $codigo) {
                return [
                    'codigo' => $codigo,
                    'nombre' => $nombre
                ];
            })->values()
        ]);
    }
}