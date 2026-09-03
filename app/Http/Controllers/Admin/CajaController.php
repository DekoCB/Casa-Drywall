<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\SesionCaja;
use App\Services\Pos\CajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CajaController extends Controller
{
    public function __construct(private readonly CajaService $cajas) {}

    public function index(): View
    {
        return view('admin.caja.index', [
            'cajas' => Caja::orderBy('nombre')->get(),
            'sesiones' => SesionCaja::with(['caja', 'usuario'])->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        Caja::create($datos + ['activo' => true]);

        return back()->with('mensaje', 'Caja creada.');
    }

    public function abrir(Request $request, Caja $caja): JsonResponse
    {
        $datos = $request->validate([
            'monto_inicial' => ['required', 'numeric', 'min:0'],
        ]);

        $sesion = $this->cajas->abrir($request->user(), $caja->id, (float) $datos['monto_inicial']);

        return response()->json($sesion);
    }

    public function cerrar(Request $request, SesionCaja $sesionCaja): JsonResponse
    {
        $datos = $request->validate([
            'monto_final_contado' => ['required', 'numeric', 'min:0'],
        ]);

        $sesion = $this->cajas->cerrar($sesionCaja, (float) $datos['monto_final_contado']);

        return response()->json($sesion);
    }
}
