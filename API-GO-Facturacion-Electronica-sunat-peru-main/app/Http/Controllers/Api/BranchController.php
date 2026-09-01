<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Controlador de Sucursales (Branches)
 *
 * Gestiona las operaciones CRUD y búsqueda de sucursales empresariales.
 * Las sucursales son establecimientos anexos donde la empresa realiza operaciones
 * comerciales y emite comprobantes electrónicos.
 *
 * @package App\Http\Controllers\Api
 */
class BranchController extends Controller
{
    /**
     * Listar todas las sucursales del sistema
     *
     * Obtiene un listado de todas las sucursales registradas con la posibilidad
     * de filtrar por empresa específica.
     *
     * @param Request $request
     * @queryParam company_id int optional ID de la empresa para filtrar sucursales
     *
     * @response {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "company_id": 1,
     *       "codigo": "0001",
     *       "nombre": "Sucursal Principal",
     *       "direccion": "Av. Principal 123",
     *       "ubigeo": "150101",
     *       "distrito": "Lima",
     *       "provincia": "Lima",
     *       "departamento": "Lima",
     *       "telefono": "01-1234567",
     *       "email": "sucursal@empresa.com",
     *       "series_factura": "F001,F002",
     *       "series_boleta": "B001",
     *       "activo": true,
     *       "company": {
     *         "id": 1,
     *         "ruc": "20123456789",
     *         "razon_social": "EMPRESA SAC"
     *       }
     *     }
     *   ],
     *   "meta": {
     *     "total": 5,
     *     "companies_count": 2
     *   }
     * }
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Branch::with(['company:id,ruc,razon_social']);

            // Filtrar por empresa si se proporciona
            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $branches = $query->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
                'meta' => [
                    'total' => $branches->count(),
                    'companies_count' => $branches->unique('company_id')->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al listar sucursales", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva sucursal
     *
     * Registra una nueva sucursal para una empresa específica.
     * Valida que la empresa exista y esté activa antes de crear la sucursal.
     *
     * @param StoreBranchRequest $request Datos validados de la sucursal
     *
     * @bodyParam company_id int required ID de la empresa. Example: 1
     * @bodyParam codigo string required Código único de la sucursal. Example: 0001
     * @bodyParam nombre string required Nombre de la sucursal. Example: Sucursal Principal
     * @bodyParam direccion string required Dirección completa. Example: Av. Principal 123
     * @bodyParam ubigeo string required Código de ubigeo (6 dígitos). Example: 150101
     * @bodyParam distrito string required Nombre del distrito. Example: Lima
     * @bodyParam provincia string required Nombre de la provincia. Example: Lima
     * @bodyParam departamento string required Nombre del departamento. Example: Lima
     * @bodyParam telefono string optional Teléfono de contacto. Example: 01-1234567
     * @bodyParam email string optional Email de contacto. Example: sucursal@empresa.com
     * @bodyParam activo boolean optional Estado de la sucursal (default: true). Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Sucursal creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "company_id": 1,
     *     "codigo": "0001",
     *     "nombre": "Sucursal Principal",
     *     "company": {
     *       "id": 1,
     *       "ruc": "20123456789",
     *       "razon_social": "EMPRESA SAC"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "La empresa especificada no existe o está inactiva"
     * }
     *
     * @return JsonResponse
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Verificar que la empresa existe y está activa
            $company = Company::where('id', $validated['company_id'])
                             ->where('activo', true)
                             ->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa especificada no existe o está inactiva'
                ], 404);
            }

            $branch = Branch::create($validated);

            Log::info("Sucursal creada exitosamente", [
                'branch_id' => $branch->id,
                'company_id' => $branch->company_id,
                'nombre' => $branch->nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal creada exitosamente',
                'data' => $branch->load('company:id,ruc,razon_social')
            ], 201);

        } catch (Exception $e) {
            Log::error("Error al crear sucursal", [
                'request_data' => $validated ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear sucursal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una sucursal específica
     *
     * Retorna la información completa de una sucursal incluyendo datos de la empresa asociada.
     *
     * @param Branch $branch Modelo de sucursal (inyección automática por route model binding)
     *
     * @urlParam branch int required ID de la sucursal. Example: 1
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "company_id": 1,
     *     "codigo": "0001",
     *     "nombre": "Sucursal Principal",
     *     "direccion": "Av. Principal 123",
     *     "ubigeo": "150101",
     *     "distrito": "Lima",
     *     "provincia": "Lima",
     *     "departamento": "Lima",
     *     "telefono": "01-1234567",
     *     "email": "sucursal@empresa.com",
     *     "series_factura": "F001,F002",
     *     "series_boleta": "B001",
     *     "activo": true,
     *     "company": {
     *       "id": 1,
     *       "ruc": "20123456789",
     *       "razon_social": "EMPRESA SAC",
     *       "nombre_comercial": "Empresa"
     *     }
     *   }
     * }
     *
     * @return JsonResponse
     */
    public function show(Branch $branch): JsonResponse
    {
        try {
            $branch->load(['company:id,ruc,razon_social,nombre_comercial']);

            return response()->json([
                'success' => true,
                'data' => $branch
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener sucursal", [
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar sucursal
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Verificar que la empresa nueva existe y está activa (si se está cambiando)
            if (isset($validated['company_id'])) {
                $company = Company::where('id', $validated['company_id'])
                                 ->where('activo', true)
                                 ->first();

                if (!$company) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La empresa especificada no existe o está inactiva'
                    ], 404);
                }
            }

            $branch->update($validated);

            Log::info("Sucursal actualizada exitosamente", [
                'branch_id' => $branch->id,
                'company_id' => $branch->company_id,
                'changes' => $branch->getChanges()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal actualizada exitosamente',
                'data' => $branch->fresh()->load('company:id,ruc,razon_social')
            ]);

        } catch (Exception $e) {
            Log::error("Error al actualizar sucursal", [
                'branch_id' => $branch->id,
                'request_data' => $validated ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar sucursal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar sucursal (soft delete - marcar como inactiva)
     */
    public function destroy(Branch $branch): JsonResponse
    {
        try {
            // Verificar si la sucursal tiene documentos asociados
            $hasDocuments = false; // Podrías implementar estas verificaciones si es necesario
            // $hasDocuments = $branch->invoices()->count() > 0 ||
            //                $branch->dispatchGuides()->count() > 0;

            if ($hasDocuments) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la sucursal porque tiene documentos asociados. Considere desactivarla en su lugar.'
                ], 400);
            }

            // Marcar como inactiva en lugar de eliminar
            $branch->update(['activo' => false]);

            Log::warning("Sucursal desactivada", [
                'branch_id' => $branch->id,
                'nombre' => $branch->nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal desactivada exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error("Error al desactivar sucursal", [
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar sucursal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar sucursal
     */
    public function activate(Branch $branch): JsonResponse
    {
        try {
            $branch->update(['activo' => true]);

            Log::info("Sucursal activada", [
                'branch_id' => $branch->id,
                'nombre' => $branch->nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal activada exitosamente',
                'data' => $branch->load('company:id,ruc,razon_social')
            ]);

        } catch (Exception $e) {
            Log::error("Error al activar sucursal", [
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al activar sucursal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener sucursales de una empresa específica con filtros
     */
    public function getByCompany(Request $request, Company $company): JsonResponse
    {
        try {
            $query = $company->branches();

            // Filtro por código
            if ($request->filled('codigo')) {
                $query->where('codigo', 'like', '%' . $request->codigo . '%');
            }

            // Filtro por ubigeo
            if ($request->filled('ubigeo')) {
                $query->where('ubigeo', $request->ubigeo);
            }

            // Filtro por nombre (búsqueda parcial)
            if ($request->filled('nombre')) {
                $query->where('nombre', 'like', '%' . $request->nombre . '%');
            }

            // Filtro por distrito
            if ($request->filled('distrito')) {
                $query->where('distrito', 'like', '%' . $request->distrito . '%');
            }

            // Filtro por provincia
            if ($request->filled('provincia')) {
                $query->where('provincia', 'like', '%' . $request->provincia . '%');
            }

            // Filtro por departamento
            if ($request->filled('departamento')) {
                $query->where('departamento', 'like', '%' . $request->departamento . '%');
            }

            // Filtro por estado activo
            if ($request->filled('activo')) {
                $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
            }

            // Búsqueda general (busca en varios campos a la vez)
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('codigo', 'like', '%' . $search . '%')
                      ->orWhere('nombre', 'like', '%' . $search . '%')
                      ->orWhere('direccion', 'like', '%' . $search . '%')
                      ->orWhere('ubigeo', 'like', '%' . $search . '%')
                      ->orWhere('distrito', 'like', '%' . $search . '%')
                      ->orWhere('provincia', 'like', '%' . $search . '%')
                      ->orWhere('departamento', 'like', '%' . $search . '%');
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'nombre');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación opcional
            if ($request->filled('per_page')) {
                $perPage = min($request->per_page, 100); // Máximo 100 por página
                $branches = $query->paginate($perPage);

                return response()->json([
                    'success' => true,
                    'data' => $branches->items(),
                    'meta' => [
                        'company_id' => $company->id,
                        'company_name' => $company->razon_social,
                        'current_page' => $branches->currentPage(),
                        'per_page' => $branches->perPage(),
                        'total' => $branches->total(),
                        'last_page' => $branches->lastPage(),
                        'from' => $branches->firstItem(),
                        'to' => $branches->lastItem()
                    ]
                ]);
            }

            // Sin paginación
            $branches = $query->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
                'meta' => [
                    'company_id' => $company->id,
                    'company_name' => $company->razon_social,
                    'total_branches' => $branches->count(),
                    'active_branches' => $branches->where('activo', true)->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener sucursales por empresa", [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar sucursal por código
     */
    public function searchByCodigo(Request $request, Company $company): JsonResponse
    {
        try {
            $codigo = $request->get('codigo');

            if (empty($codigo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro "codigo" es requerido'
                ], 400);
            }

            $branch = $company->branches()
                             ->where('codigo', $codigo)
                             ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ninguna sucursal con el código proporcionado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $branch
            ]);

        } catch (Exception $e) {
            Log::error("Error al buscar sucursal por código", [
                'company_id' => $company->id,
                'codigo' => $codigo ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar sucursal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar sucursal por ubigeo
     */
    public function searchByUbigeo(Request $request, Company $company): JsonResponse
    {
        try {
            $ubigeo = $request->get('ubigeo');

            if (empty($ubigeo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro "ubigeo" es requerido'
                ], 400);
            }

            $branches = $company->branches()
                               ->where('ubigeo', $ubigeo)
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
                'meta' => [
                    'company_id' => $company->id,
                    'ubigeo' => $ubigeo,
                    'total' => $branches->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al buscar sucursales por ubigeo", [
                'company_id' => $company->id,
                'ubigeo' => $ubigeo ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar sucursales: ' . $e->getMessage()
            ], 500);
        }
    }
}