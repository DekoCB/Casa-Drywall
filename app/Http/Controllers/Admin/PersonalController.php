<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PersonalController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $personal = Personal::query()
            ->where('personal.estado', 'activo')
            ->leftJoin('usuarios as u', 'u.id', '=', 'personal.usuario_id')
            ->select('personal.*', 'u.username as acceso_username', 'u.rol as acceso_rol')
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('personal.nombres', 'like', "%{$busqueda}%")
                        ->orWhere('personal.apellidos', 'like', "%{$busqueda}%")
                        ->orWhere('personal.dni', 'like', "%{$busqueda}%")
                        ->orWhere('personal.cargo', 'like', "%{$busqueda}%");
                });
            })
            ->orderByDesc('personal.id')
            ->paginate(15)
            ->withQueryString();

        $resumen = Personal::where('estado', 'activo')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(sueldo), 0) AS planilla, COUNT(DISTINCT area) AS areas')
            ->first();

        return view('admin.personal.index', [
            'personal' => $personal,
            'busqueda' => $busqueda,
            'totalActivos' => (int) $resumen->total,
            'totalPlanilla' => (float) $resumen->planilla,
            'totalAreas' => (int) $resumen->areas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($request, $datos) {
            $empleado = Personal::create($datos);
            $this->sincronizarAcceso($request, $empleado);
        });

        return redirect()->route('admin.personal.index')->with('mensaje', 'Personal registrado exitosamente');
    }

    public function update(Request $request, Personal $personal): RedirectResponse
    {
        $datos = $this->validar($request, $personal);

        DB::transaction(function () use ($request, $personal, $datos) {
            $personal->update($datos);
            $this->sincronizarAcceso($request, $personal);
        });

        return redirect()->route('admin.personal.index')->with('mensaje', 'Personal actualizado exitosamente');
    }

    /** Baja lógica, igual que el módulo original. */
    public function destroy(Personal $personal): RedirectResponse
    {
        $personal->update(['estado' => 'inactivo']);

        return redirect()->route('admin.personal.index')->with('mensaje', 'Personal dado de baja');
    }

    /**
     * Crea o actualiza el usuario del sistema vinculado al empleado cuando el
     * formulario incluye credenciales de acceso.
     */
    private function sincronizarAcceso(Request $request, Personal $empleado): void
    {
        $username = trim((string) $request->input('acceso_username', ''));

        if ($username === '') {
            return;
        }

        $rol = $request->input('acceso_rol', 'secretaria');
        $password = $request->input('acceso_password');

        if ($empleado->usuario_id && $usuario = Usuario::find($empleado->usuario_id)) {
            $usuario->username = $username;
            $usuario->rol = $rol;

            if (filled($password)) {
                $usuario->password = $password;
            }

            $usuario->save();

            return;
        }

        $usuario = Usuario::create([
            'username' => $username,
            'email' => $empleado->email,
            'password' => filled($password) ? $password : str()->random(12),
            'rol' => $rol,
        ]);

        $empleado->update(['usuario_id' => $usuario->id]);
    }

    private function validar(Request $request, ?Personal $personal = null): array
    {
        return $request->validate([
            'dni' => ['required', 'string', 'max:15', Rule::unique('personal', 'dni')->ignore($personal?->id)],
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'cargo' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'fecha_ingreso' => ['nullable', 'date'],
            'sueldo' => ['nullable', 'numeric', 'min:0'],
            'tipo_contrato' => ['required', 'string', 'max:50'],
        ]);
    }
}
