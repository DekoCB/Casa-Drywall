<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\Producto;
use App\Services\AnalizadorFacturaIA;
use App\Services\MatrizGalonaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Facturas pendientes por pagar a GP Maquinarias SAC.
 *
 * Migrado de `administrador/facturas.php`: el listado se agrupa por mes de
 * emisión, los totales sólo cuentan las facturas activas (ni canceladas ni
 * pagadas) y el galonaje suma todas.
 */
class FacturaController extends Controller
{
    public function index(Request $request, MatrizGalonaje $matriz): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $mes      = trim((string) $request->query('mes', ''));    // YYYY-MM
        $desde    = trim((string) $request->query('desde', ''));
        $hasta    = trim((string) $request->query('hasta', ''));

        $facturas = Factura::query()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numero', 'like', "%{$busqueda}%")
                        ->orWhere('doc', 'like', "%{$busqueda}%")
                        ->orWhere('guia_remision', 'like', "%{$busqueda}%")
                        ->orWhere('producto', 'like', "%{$busqueda}%")
                        ->orWhere('cliente', 'like', "%{$busqueda}%");
                });
            })
            ->when($mes !== '', fn ($q) => $q->whereRaw("DATE_FORMAT(emision, '%Y-%m') = ?", [$mes]))
            ->when($desde !== '', fn ($q) => $q->whereDate('emision', '>=', $desde))
            ->when($hasta !== '', fn ($q) => $q->whereDate('emision', '<=', $hasta))
            ->orderByDesc('emision')
            ->orderBy('numero')
            ->get();

        // Mes más reciente primero; dentro de cada mes, por número ascendente.
        $grupos = $facturas
            ->groupBy(fn (Factura $f) => $f->emision->format('Y-m'))
            ->map(fn ($grupo) => $grupo->sortBy('numero')->values())
            ->sortKeysDesc();

        $activas = $facturas->filter(fn (Factura $f) => $f->estaActiva());

        return view('admin.facturas.index', [
            'grupos'     => $grupos,
            'busqueda'   => $busqueda,
            'mesSel'     => $mes,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'nActivas'   => $activas->count(),
            'nTotal'     => $facturas->count(),
            // Los totales cuentan sólo lo que sigue por pagar…
            'totalUsd'   => (float) $activas->sum('importe'),
            'totalSoles' => $activas->sum(fn (Factura $f) => (float) $f->importe * (float) $f->tc),
            // …salvo el galonaje, que acumula todas las facturas.
            'totalGal'   => (float) $facturas->sum('galones'),
            // El buscador de productos del modal y su factor de galonaje.
            'catalogo'   => $this->catalogoProductos($matriz),
            'factores'   => $matriz->productos(),
        ]);
    }

    /**
     * Lista para el autocompletado de productos del modal: los productos
     * registrados más los de la matriz de galonaje, sin repetir códigos.
     */
    private function catalogoProductos(MatrizGalonaje $matriz): array
    {
        $catalogo = [];

        foreach (Producto::orderBy('nombre')->get(['codigo', 'nombre', 'presentacion']) as $p) {
            $clave = trim((string) $p->codigo).'|'.$p->nombre;
            $catalogo[$clave] = ['cod' => (string) $p->codigo, 'n' => $p->nombre, 'c' => $p->presentacion ?? ''];
        }

        foreach ($matriz->productos() as $codigo => $datos) {
            $clave = $codigo.'|'.($datos['n'] ?? '');

            if (! isset($catalogo[$clave])) {
                $catalogo[$clave] = ['cod' => (string) $codigo, 'n' => $datos['n'] ?? '', 'c' => $datos['l'] ?? ''];
            }
        }

        return array_values($catalogo);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        if (Factura::where('numero', $datos['numero'])->exists()) {
            return back()->with('error', "La factura {$datos['numero']} ya existe en el sistema.");
        }

        Factura::create($datos);

        return back()->with('mensaje', "Factura {$datos['numero']} registrada.");
    }

    public function update(Request $request, Factura $factura): RedirectResponse
    {
        $datos = $this->validar($request);

        if (Factura::where('numero', $datos['numero'])->where('id', '!=', $factura->id)->exists()) {
            return back()->with('error', "Ya existe otra factura con el número {$datos['numero']}.");
        }

        $factura->update($datos);

        return back()->with('mensaje', "Factura {$factura->numero} actualizada.");
    }

    public function destroy(Factura $factura): RedirectResponse
    {
        $numero = $factura->numero;

        if ($factura->pdf) {
            Storage::disk('public')->delete($factura->pdf);
        }

        $factura->delete();

        return back()->with('mensaje', "Factura {$numero} eliminada.");
    }

    /** Anula o reactiva la factura, sin borrarla. */
    public function alternarCancelado(Factura $factura): JsonResponse
    {
        $factura->update(['cancelado' => ! $factura->cancelado]);

        return response()->json([
            'ok'        => true,
            'cancelado' => $factura->cancelado,
            'estado'    => $factura->estado(),
        ]);
    }

    /** Marca la factura como pagada o la devuelve al estado automático. */
    public function alternarPagada(Factura $factura): JsonResponse
    {
        $factura->update(['estado_manual' => $factura->estado_manual === 'pagada' ? '' : 'pagada']);

        return response()->json([
            'ok'     => true,
            'pagada' => $factura->estado_manual === 'pagada',
            'estado' => $factura->estado(),
        ]);
    }

    public function subirPdf(Request $request, Factura $factura): RedirectResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        if ($factura->pdf) {
            Storage::disk('public')->delete($factura->pdf);
        }

        $factura->update([
            'pdf' => $request->file('pdf')->storeAs('facturas', $factura->archivoPdf(), 'public'),
        ]);

        return back()->with('mensaje', "PDF adjuntado a la factura {$factura->numero}.");
    }

    public function eliminarPdf(Factura $factura): RedirectResponse
    {
        if ($factura->pdf) {
            Storage::disk('public')->delete($factura->pdf);
            $factura->update(['pdf' => null]);
        }

        return back()->with('mensaje', "PDF quitado de la factura {$factura->numero}.");
    }

    public function show(Factura $factura): View
    {
        return view('admin.facturas.show', compact('factura'));
    }

    /** Panel de estadísticas de las facturas pendientes. */
    /**
     * Análisis del saldo en moneda extranjera, transcrito de
     * `administrador/estadisticas_facturas.php`. Todo se calcula sobre las
     * facturas activas salvo el galonaje, que incluye canceladas.
     */
    public function estadisticas(): View
    {
        $facturas = Factura::orderBy('emision')->get();
        $activas  = $facturas->filter(fn (Factura $f) => $f->estaActiva());

        $soles = fn (Factura $f) => (float) $f->importe * (float) $f->tc;

        $totalUsd = (float) $activas->sum('importe');

        // ── Reparto mensual, base de los tres gráficos ──────────────────────
        $acumUsd = 0.0;
        $acumSoles = 0.0;

        $porMes = $activas
            ->groupBy(fn (Factura $f) => $f->emision->format('Y-m'))
            ->sortKeys()
            ->map(function ($grupo, $mes) use ($soles, $totalUsd, &$acumUsd, &$acumSoles) {
                $usd = (float) $grupo->sum('importe');
                $sol = (float) $grupo->sum($soles);

                $acumUsd += $usd;
                $acumSoles += $sol;

                return [
                    'mes'        => $mes,
                    'etiqueta'   => ucfirst(Carbon::createFromFormat('Y-m', $mes)->translatedFormat('F Y')),
                    'n'          => $grupo->count(),
                    'usd'        => round($usd, 2),
                    'soles'      => round($sol, 2),
                    'promedio'   => round($usd / max(1, $grupo->count()), 2),
                    'porcentaje' => $totalUsd > 0 ? round($usd / $totalUsd * 100, 1) : 0.0,
                    'acum_usd'   => round($acumUsd, 2),
                    'acum_soles' => round($acumSoles, 2),
                ];
            })
            ->values();

        // ── Reparto por producto, ordenado por importe en dólares ───────────
        $porProducto = $activas
            ->groupBy(fn (Factura $f) => trim((string) $f->producto) ?: 'Sin producto')
            ->map(fn ($grupo, $producto) => [
                'producto' => $producto,
                'usd'      => round((float) $grupo->sum('importe'), 2),
                'soles'    => round((float) $grupo->sum($soles), 2),
            ])
            ->sortByDesc('usd')
            ->values();

        $mesMax = $porMes->sortByDesc('usd')->first();

        return view('admin.facturas.estadisticas', [
            'porMes'      => $porMes,
            'porProducto' => $porProducto,
            'mesMax'      => $mesMax,
            'nTotal'      => $facturas->count(),
            'nActivas'    => $activas->count(),
            'nCanceladas' => $facturas->count() - $activas->count(),
            'totalUsd'    => $totalUsd,
            'totalSoles'  => round($activas->sum($soles), 2),
            'totalGal'    => (float) $facturas->sum('galones'),
            'promedio'    => $activas->count() > 0 ? round($totalUsd / $activas->count(), 2) : 0.0,
            'tcPromedio'  => $activas->count() > 0 ? round((float) $activas->avg('tc'), 4) : 0.0,
        ]);
    }

    /**
     * Extrae los datos de una factura en PDF con ayuda de un modelo de lenguaje
     * y les aplica la matriz de galonaje, igual que `analizar_factura.php`.
     */
    public function analizar(Request $request, AnalizadorFacturaIA $analizador, MatrizGalonaje $matriz): JsonResponse
    {
        $request->validate(['pdf_file' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $resultado = $analizador->analizar($request->file('pdf_file')->getRealPath());

        if (! ($resultado['ok'] ?? false)) {
            return response()->json($resultado, 422);
        }

        // Se enriquece cada ítem con su factor de galonaje y su línea de producto.
        $resultado['items'] = array_map(
            fn (array $item) => $item + $matriz->datosDe($item['codigo'] ?? ''),
            $resultado['items'] ?? []
        );

        $resultado['galones_total'] = array_sum(array_map(
            fn (array $item) => (float) ($item['cantidad'] ?? 0) * (float) ($item['factor_galones'] ?? 0),
            $resultado['items']
        ));

        return response()->json($resultado);
    }

    private function validar(Request $request): array
    {
        // El detalle de productos viaja serializado desde el formulario.
        if (is_string($request->input('productos_lista'))) {
            $request->merge(['productos_lista' => json_decode($request->input('productos_lista'), true) ?: []]);
        }

        return $request->validate([
            'numero'          => ['required', 'string', 'max:40'],
            'doc'             => ['nullable', 'string', 'max:40'],
            'guia_remision'   => ['nullable', 'string', 'max:60'],
            'emision'         => ['required', 'date'],
            'vencimiento'     => ['required', 'date'],
            'importe'         => ['required', 'numeric', 'min:0'],
            'tc'              => ['required', 'numeric', 'min:0'],
            'galones'         => ['nullable', 'numeric', 'min:0'],
            'producto'        => ['nullable', 'string', 'max:255'],
            'cliente'         => ['nullable', 'string', 'max:255'],
            'estado_manual'   => ['nullable', 'in:'.implode(',', Factura::ESTADOS)],
            'productos_lista' => ['nullable', 'array'],
        ]);
    }
}
