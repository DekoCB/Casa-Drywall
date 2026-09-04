<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Egreso;
use App\Services\SincronizadorEgresos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EgresoController extends Controller
{
    /** Etiqueta legible para cada origen automático. */
    public const ORIGENES = [
        'manual' => 'Manual',
        'venta_flete' => 'Flete de venta',
        'venta_gasolina' => 'Gasolina de venta',
        'venta_promo' => 'Promoción de venta',
        'orden_compra' => 'Orden de compra',
        'activo_fijo' => 'Activo fijo',
        'planilla' => 'Planilla',
    ];

    public function index(Request $request, SincronizadorEgresos $sincronizador): View
    {
        $mes = (int) $request->query('mes', now()->month);
        $anio = (int) $request->query('anio', now()->year);
        $tipo = trim((string) $request->query('tipo', ''));

        // El módulo original sincroniza al entrar, para que el mes consultado
        // siempre refleje los egresos derivados de ventas, OC y planilla.
        $sincronizador->sincronizar($anio, $mes);

        $egresos = Egreso::whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->when($tipo !== '', fn ($q) => $q->where('tipo', $tipo))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $porTipo = Egreso::selectRaw('tipo, SUM(monto) AS total, COUNT(*) AS n')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->get();

        $manual = Egreso::whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->where('origen', 'manual')
            ->selectRaw('COALESCE(SUM(monto), 0) AS total, COUNT(*) AS n')
            ->first();

        return view('admin.egresos.index', [
            'egresos' => $egresos,
            'porTipo' => $porTipo,
            'mes' => $mes,
            'anio' => $anio,
            'tipoSel' => $tipo,
            'totalMes' => (float) $porTipo->sum('total'),
            'totalManual' => (float) $manual->total,
            'nManual' => (int) $manual->n,
            'almacenes' => Almacen::where('activo', true)->orderBy('id')->get(),
            'origenes' => self::ORIGENES,
            'anios' => $this->aniosDisponibles(),
            'abrirCrear' => $request->boolean('crear'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['usuario_id'] = $request->user()->id;
        $datos['origen'] = 'manual';

        Egreso::create($datos);

        return back()->with('mensaje', 'Egreso registrado exitosamente');
    }

    public function update(Request $request, Egreso $egreso): RedirectResponse
    {
        if ($egreso->origen !== 'manual') {
            return back()->with('error', 'Los egresos automáticos no se editan a mano; se regeneran desde su módulo de origen.');
        }

        $egreso->update($this->validar($request));

        return back()->with('mensaje', 'Egreso actualizado exitosamente');
    }

    /** Solo se borran los manuales; los automáticos los controla su origen. */
    public function destroy(Egreso $egreso): RedirectResponse
    {
        if ($egreso->origen !== 'manual') {
            return back()->with('error', 'Este egreso es automático y no puede eliminarse manualmente.');
        }

        $egreso->delete();

        return back()->with('mensaje', 'Egreso eliminado exitosamente');
    }

    public function sincronizar(Request $request, SincronizadorEgresos $sincronizador): RedirectResponse
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes = (int) $request->input('mes', now()->month);

        $creados = $sincronizador->sincronizar($anio, $mes);
        $total = array_sum($creados);

        return back()->with('mensaje', $total > 0
            ? "Sincronización completada: {$total} egreso(s) automático(s) generado(s)."
            : 'Sincronización completada: no había egresos nuevos por generar.');
    }

    private function aniosDisponibles(): array
    {
        // `YEAR(fecha)` era SQL exclusivo de MySQL; se calcula en PHP para
        // que también funcione en los tests (SQLite).
        $anios = Egreso::pluck('fecha')->filter()->map(fn ($f) => $f->year)->unique()->sortDesc()->values()->all();

        return $anios ?: [now()->year];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'string', 'max:50'],
            'categoria' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0'],
            'almacen_id' => ['nullable', 'integer', 'exists:almacenes,id'],
        ]);
    }
}
