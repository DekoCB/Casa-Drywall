<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['role', 'company']);

            // Filtros
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('role_id')) {
                $query->where('role_id', $request->role_id);
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            if ($request->has('active')) {
                $query->where('active', $request->active);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request)
    {
        // Verificar permisos
        if (!$request->user()->hasRole('super_admin') && !$request->user()->hasPermission('users.create')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para crear usuarios'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_name' => 'required|string|exists:roles,name',
            'company_id' => 'nullable|integer|exists:companies,id',
            'user_type' => 'required|in:system,user,api_client',
            'active' => 'boolean'
        ]);

        try {
            $role = Role::where('name', $request->role_name)->first();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id,
                'company_id' => $request->company_id,
                'user_type' => $request->user_type,
                'active' => $request->get('active', true),
                'email_verified_at' => now(),
            ]);

            $user->load('role', 'company');

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name
                    ] : null,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'razon_social' => $user->company->razon_social
                    ] : null,
                    'user_type' => $user->user_type,
                    'active' => $user->active,
                    'created_at' => $user->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un usuario específico
     */
    public function show($id)
    {
        try {
            $user = User::with(['role', 'company'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name
                    ] : null,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'razon_social' => $user->company->razon_social,
                        'ruc' => $user->company->ruc
                    ] : null,
                    'user_type' => $user->user_type,
                    'active' => $user->active,
                    'last_login_at' => $user->last_login_at,
                    'last_login_ip' => $user->last_login_ip,
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->locked_until,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar un usuario
     */
    public function update(Request $request, $id)
    {
        // Verificar permisos
        if (!$request->user()->hasRole('super_admin') && !$request->user()->hasPermission('users.update')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar usuarios'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            // No permitir que un usuario se desactive a sí mismo
            if ($user->id === $request->user()->id && $request->has('active') && !$request->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes desactivarte a ti mismo'
                ], 400);
            }

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'password' => ['sometimes', 'confirmed', Password::min(8)],
                'role_name' => 'sometimes|required|string|exists:roles,name',
                'company_id' => 'nullable|integer|exists:companies,id',
                'user_type' => 'sometimes|required|in:system,user,api_client',
                'active' => 'sometimes|boolean'
            ]);

            // Actualizar campos
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }

            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            if ($request->has('role_name')) {
                $role = Role::where('name', $request->role_name)->first();
                $user->role_id = $role->id;
            }

            if ($request->has('company_id')) {
                $user->company_id = $request->company_id;
            }

            if ($request->has('user_type')) {
                $user->user_type = $request->user_type;
            }

            if ($request->has('active')) {
                $user->active = $request->active;
            }

            $user->save();
            $user->load('role', 'company');

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name
                    ] : null,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'razon_social' => $user->company->razon_social
                    ] : null,
                    'user_type' => $user->user_type,
                    'active' => $user->active,
                    'updated_at' => $user->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un usuario
     */
    public function destroy(Request $request, $id)
    {
        // Verificar permisos
        if (!$request->user()->hasRole('super_admin') && !$request->user()->hasPermission('users.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar usuarios'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            // No permitir que un usuario se elimine a sí mismo
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminarte a ti mismo'
                ], 400);
            }

            // Verificar si es el único super admin
            if ($user->hasRole('super_admin')) {
                $superAdminCount = User::whereHas('role', function($query) {
                    $query->where('name', 'super_admin');
                })->where('active', true)->count();

                if ($superAdminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes eliminar el único super admin del sistema'
                    ], 400);
                }
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar/Desactivar usuario
     */
    public function toggleActive(Request $request, $id)
    {
        // Verificar permisos
        if (!$request->user()->hasRole('super_admin') && !$request->user()->hasPermission('users.update')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para cambiar el estado de usuarios'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            // No permitir que un usuario se desactive a sí mismo
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes desactivarte a ti mismo'
                ], 400);
            }

            $user->active = !$user->active;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => $user->active ? 'Usuario activado exitosamente' : 'Usuario desactivado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'active' => $user->active
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado del usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desbloquear usuario bloqueado
     */
    public function unlock(Request $request, $id)
    {
        // Verificar permisos
        if (!$request->user()->hasRole('super_admin') && !$request->user()->hasPermission('users.update')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para desbloquear usuarios'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Usuario desbloqueado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->locked_until
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desbloquear usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resetear contraseña de un usuario
     */
    public function resetPassword(Request $request, $id)
    {
        // Verificar permisos
        if (!$request->user()->hasRole('super_admin') && !$request->user()->hasPermission('users.update')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para resetear contraseñas'
            ], 403);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)]
        ]);

        try {
            $user = User::findOrFail($id);

            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al resetear contraseña: ' . $e->getMessage()
            ], 500);
        }
    }
}
