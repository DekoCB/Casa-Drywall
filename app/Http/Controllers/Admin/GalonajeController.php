<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Services\MatrizGalonaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Módulo de galonaje. La matriz de productos, sus categorías y presentaciones
 * viven en archivos JSON, igual que en el proyecto original.
 */
class GalonajeController extends Controller
{
    public function __construct(private readonly MatrizGalonaje $matriz) {}

    /** Meses que abarca cada trimestre y cada bimestre. */
    private const TRIMESTRES = [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8, 9], 4 => [10, 11, 12]];

    private const BIMESTRES = [1 => [1, 2], 2 => [3, 4], 3 => [5, 6], 4 => [7, 8], 5 => [9, 10], 6 => [11, 12]];

    /**
     * Consumo de galones por mes, producto y cliente. Los galones viven en las
     * facturas de GP Maquinarias, no en las ventas: es el mismo origen que usa
     * `administrador/galonaje_dashboard.php`.
     */
    public function dashboard(Request $request): View
    {
        $filtros = [
            'anio'      => (string) $request->query('anio', ''),
            'mes'       => (string) $request->query('mes', ''),
            'trimestre' => (string) $request->query('trimestre', ''),
            'bimestre'  => (string) $request->query('bimestre', ''),
            'desde'     => (string) $request->query('desde', ''),
            'hasta'     => (string) $request->query('hasta', ''),
            'producto'  => (string) $request->query('producto', ''),
            'estado'    => (string) $request->query('estado', ''),
        ];

        $todas = Factura::orderBy('emision')->get();

        // Los desplegables se arman con todo, no con lo ya filtrado.
        $anios = $todas->map(fn (Factura $f) => $f->emision?->year)->filter()->unique()->sortDesc()->values();
        $productos = $todas->map(fn (Factura $f) => trim((string) $f->producto))
            ->filter()->unique()->sort()->values();

        $facturas = $todas
            ->filter(fn (Factura $f) => (float) $f->galones > 0)
            ->filter(fn (Factura $f) => $this->pasaFiltros($f, $filtros))
            ->values();

        $porMes = $facturas
            ->groupBy(fn (Factura $f) => $f->emision->format('Y-m'))
            ->sortKeys()
            ->map(fn ($grupo, $mes) => [
                'mes'      => $mes,
                'etiqueta' => ucfirst(Carbon::createFromFormat('Y-m', $mes)->translatedFormat('F Y')),
                'galones'  => round((float) $grupo->sum('galones'), 2),
                'facturas' => $grupo->count(),
                'importe'  => round((float) $grupo->sum('importe'), 2),
            ])
            ->values();

        $agrupar = fn (string $campo, string $vacio) => $facturas
            ->groupBy(fn (Factura $f) => trim((string) $f->{$campo}) ?: $vacio)
            ->map(fn ($grupo, $clave) => [
                'nombre'   => $clave,
                'galones'  => round((float) $grupo->sum('galones'), 2),
                'facturas' => $grupo->count(),
                'importe'  => round((float) $grupo->sum('importe'), 2),
            ])
            ->sortByDesc('galones')
            ->values();

        $porProducto = $agrupar('producto', 'Sin producto');
        $totalGalones = round((float) $facturas->sum('galones'), 2);

        $topFacturas = $facturas->sortByDesc(fn (Factura $f) => (float) $f->galones)->take(20)->values();

        $anioMetas = (int) ($filtros['anio'] ?: now()->year);

        return view('admin.galonaje.dashboard', [
            'filtros'       => $filtros,
            'anios'         => $anios,
            'productos'     => $productos,
            'porMes'        => $porMes,
            'porProducto'   => $porProducto,
            'porCliente'    => $agrupar('cliente', 'Sin cliente'),
            'topFacturas'   => $topFacturas,
            'totalGalones'  => $totalGalones,
            'nFacturas'     => $facturas->count(),
            'mejorMes'      => $porMes->sortByDesc('galones')->first(),
            'productoTop'   => $porProducto->first(),
            'promedioMes'   => $porMes->count() > 0 ? round($totalGalones / $porMes->count(), 2) : 0.0,
            'nMeses'        => $porMes->count(),
            'anioMetas'     => $anioMetas,
            'metas'         => $this->metasDelAnio($anioMetas),
        ]);
    }

    /** Guarda de una vez las metas mensuales de galones de un año. */
    public function guardarMetas(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'anio_meta' => ['required', 'integer', 'min:2000', 'max:2100'],
            'meta' => ['required', 'array'],
            'meta.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach (range(1, 12) as $mes) {
            $this->matriz->guardarMeta(
                sprintf('%04d-%02d', $datos['anio_meta'], $mes),
                ['meta_galones' => (float) ($datos['meta'][$mes] ?? 0)]
            );
        }

        return back()->with('mensaje', "Metas de {$datos['anio_meta']} guardadas.");
    }

    /** Metas mensuales del año indicado, indexadas por número de mes. */
    private function metasDelAnio(int $anio): array
    {
        $metas = [];

        foreach ($this->matriz->metas() as $periodo => $datos) {
            [$a, $m] = array_pad(explode('-', (string) $periodo), 2, null);

            if ((int) $a === $anio && $m !== null) {
                $metas[(int) $m] = (float) ($datos['meta_galones'] ?? 0);
            }
        }

        return $metas;
    }

    /** Aplica los ocho filtros del panel sobre una factura. */
    private function pasaFiltros(Factura $factura, array $filtros): bool
    {
        $emision = $factura->emision;

        if ($filtros['anio'] !== '' && (string) $emision->year !== $filtros['anio']) {
            return false;
        }

        if ($filtros['mes'] !== '' && (int) $emision->month !== (int) $filtros['mes']) {
            return false;
        }

        if ($filtros['trimestre'] !== ''
            && ! in_array($emision->month, self::TRIMESTRES[(int) $filtros['trimestre']] ?? [], true)) {
            return false;
        }

        if ($filtros['bimestre'] !== ''
            && ! in_array($emision->month, self::BIMESTRES[(int) $filtros['bimestre']] ?? [], true)) {
            return false;
        }

        if ($filtros['desde'] !== '' && $emision->toDateString() < $filtros['desde']) {
            return false;
        }

        if ($filtros['hasta'] !== '' && $emision->toDateString() > $filtros['hasta']) {
            return false;
        }

        if ($filtros['producto'] !== '' && trim((string) $factura->producto) !== $filtros['producto']) {
            return false;
        }

        return match ($filtros['estado']) {
            'activa' => ! $factura->cancelado,
            'cancelada' => (bool) $factura->cancelado,
            default => true,
        };
    }

    // ── Productos de la matriz ──────────────────────────────────────────────

    public function productos(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));
        $linea = $request->query('linea');

        $productos = collect($this->matriz->productos())
            ->map(fn (array $datos, string $codigo) => (object) [
                'codigo' => $codigo,
                'factor' => (float) ($datos['f'] ?? 0),
                'presentacion' => $datos['p'] ?? '',
                'nombre' => $datos['n'] ?? '',
                'linea' => $datos['l'] ?? '',
            ])
            ->when($busqueda !== '', fn ($c) => $c->filter(
                fn ($p) => str_contains(mb_strtolower($p->nombre), mb_strtolower($busqueda))
                    || str_contains($p->codigo, $busqueda)
            ))
            ->when($linea, fn ($c) => $c->where('linea', $linea))
            ->sortBy('nombre')
            ->values();

        return view('admin.galonaje.productos', [
            'productos' => $productos,
            'busqueda' => $busqueda,
            'lineaSel' => $linea,
            'categorias' => $this->matriz->categorias(),
            'presentaciones' => $this->matriz->presentaciones(),
        ]);
    }

    public function guardarProducto(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'factor' => ['required', 'numeric', 'min:0'],
            'presentacion' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:255'],
            'linea' => ['required', 'string', 'max:20'],
        ]);

        $this->matriz->guardarProducto($datos['codigo'], [
            'f' => $datos['factor'],
            'p' => $datos['presentacion'],
            'n' => $datos['nombre'],
            'l' => $datos['linea'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function eliminarProducto(string $codigo): JsonResponse
    {
        $this->matriz->eliminarProducto($codigo);

        return response()->json(['ok' => true]);
    }

    // ── Categorías (líneas de producto) ─────────────────────────────────────

    /** El mantenimiento vive ahora en la pestaña «Categorías» del catálogo. */
    public function categorias(): RedirectResponse
    {
        return redirect()->route('admin.productos.categorias');
    }

    public function guardarCategoria(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'codigo_orig' => ['nullable', 'string', 'max:20'],
        ]);

        $this->matriz->guardarCategoria(
            $datos['codigo'],
            $datos['descripcion'] ?? '',
            $datos['codigo_orig'] ?? null
        );

        return response()->json(['ok' => true]);
    }

    public function eliminarCategoria(Request $request, string $codigo): JsonResponse
    {
        $productos = $this->matriz->productos();
        $enUso = count(array_filter($productos, fn ($p) => ($p['l'] ?? '') === strtoupper(trim($codigo))));

        // El original pide confirmación antes de borrar una categoría en uso.
        if ($enUso > 0 && ! $request->boolean('forzar')) {
            return response()->json([
                'ok' => false,
                'en_uso' => $enUso,
                'requiere_confirmacion' => true,
            ]);
        }

        $this->matriz->eliminarCategoria($codigo);

        return response()->json(['ok' => true]);
    }

    // ── Presentaciones ──────────────────────────────────────────────────────

    /** El mantenimiento vive ahora en la pestaña «Presentaciones» del catálogo. */
    public function presentaciones(): RedirectResponse
    {
        return redirect()->route('admin.productos.presentaciones');
    }

    public function guardarPresentacion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'factor' => ['required', 'numeric', 'min:0'],
        ]);

        // Se conserva el formato del original: el factor va en `gl`.
        $this->matriz->guardarPresentacion($datos['codigo'], [
            'gl' => (float) $datos['factor'],
            'descripcion' => $datos['descripcion'] ?? '',
        ]);

        return response()->json(['ok' => true]);
    }

    public function eliminarPresentacion(string $codigo): JsonResponse
    {
        $this->matriz->eliminarPresentacion($codigo);

        return response()->json(['ok' => true]);
    }

    // ── Metas ───────────────────────────────────────────────────────────────

    public function guardarMeta(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'meta_galones' => ['required', 'numeric', 'min:0'],
            'meta_monto' => ['nullable', 'numeric', 'min:0'],
        ]);

        $periodo = sprintf('%04d-%02d', $datos['anio'], $datos['mes']);

        $this->matriz->guardarMeta($periodo, [
            'meta_galones' => (float) $datos['meta_galones'],
            'meta_monto' => (float) ($datos['meta_monto'] ?? 0),
        ]);

        return response()->json(['ok' => true]);
    }
}
