<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cobranza;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Resumen ejecutivo del negocio.
 *
 * Todo el panel se apoya en las dos fuentes que sí están pobladas: el registro de
 * ventas y la cartera de cobranzas. Galonaje, ingresos y egresos quedan fuera
 * porque hoy no tienen datos suficientes para graficarse sin engañar.
 */
class DashboardController extends Controller
{
    /** Períodos que ofrece el selector, con su etiqueta. */
    public const PERIODOS = [
        '30d' => 'Últimos 30 días',
        'mes' => 'Mes actual',
        'trimestre' => 'Trimestre',
        'anio' => 'Año',
        'todo' => 'Histórico',
    ];

    /** Tramos de antigüedad de la deuda, en días vencidos. */
    private const TRAMOS = [
        ['clave' => 'por_vencer', 'etiqueta' => 'Por vencer', 'desde' => null, 'hasta' => 0],
        ['clave' => 'd1_30',      'etiqueta' => '1 – 30 días', 'desde' => 1,   'hasta' => 30],
        ['clave' => 'd31_60',     'etiqueta' => '31 – 60 días', 'desde' => 31, 'hasta' => 60],
        ['clave' => 'd61_90',     'etiqueta' => '61 – 90 días', 'desde' => 61, 'hasta' => 90],
        ['clave' => 'd90_mas',    'etiqueta' => 'Más de 90 días', 'desde' => 91, 'hasta' => null],
    ];

    public function index(Request $request): View
    {
        $periodo = array_key_exists($request->query('periodo'), self::PERIODOS)
            ? $request->query('periodo')
            : '30d';

        [$desde, $hasta] = $this->rango($periodo);
        [$desdePrev, $hastaPrev] = $this->rangoAnterior($desde, $hasta, $periodo);

        $actual = $this->agregadosVentas($desde, $hasta);
        $previo = $desdePrev ? $this->agregadosVentas($desdePrev, $hastaPrev) : null;

        $facturado = (float) $actual->facturado;
        $cobrado = (float) $actual->cobrado;

        $antiguedad = $this->antiguedadDeuda();

        return view('admin.dashboard', [
            'periodo' => $periodo,
            'periodos' => self::PERIODOS,
            'desde' => $desde,
            'hasta' => $hasta,

            // ── KPIs ────────────────────────────────────────────────────────
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'nComprobantes' => (int) $actual->n,
            'ticket' => $actual->n > 0 ? $facturado / (int) $actual->n : 0.0,
            'porCobrar' => $this->saldoPorCobrar(),
            'vencido90' => $antiguedad['d90_mas'],
            'stockBajo' => $this->productosStockBajo(),

            // Sin período previo comparable en el histórico, no se inventa un delta.
            'facturadoPct' => $previo ? $this->variacion($facturado, (float) $previo->facturado) : null,
            'cobradoPct' => $previo ? $this->variacion($cobrado, (float) $previo->cobrado) : null,
            'comprobantesPct' => $previo ? $this->variacion((int) $actual->n, (int) $previo->n) : null,

            // ── Gráficos ────────────────────────────────────────────────────
            'tendencia' => $this->tendenciaMensual(24),
            'antiguedad' => $antiguedad,
            'tramos' => self::TRAMOS,
            'facturadoVsCobrado' => $this->tendenciaMensual(12),
            'topDeudores' => $this->topDeudores(),
            'criticas' => $this->cobranzasCriticas(),
        ]);
    }

    // ── Rangos de fecha ─────────────────────────────────────────────────────

    /** @return array{0: Carbon, 1: Carbon} */
    private function rango(string $periodo): array
    {
        $hoy = Carbon::today();

        return match ($periodo) {
            'mes' => [$hoy->copy()->startOfMonth(), $hoy],
            'trimestre' => [$hoy->copy()->startOfQuarter(), $hoy],
            'anio' => [$hoy->copy()->startOfYear(), $hoy],
            'todo' => [$this->primeraVenta(), $hoy],
            default => [$hoy->copy()->subDays(29), $hoy],
        };
    }

    /**
     * Período inmediatamente anterior, del mismo largo, para comparar peras con
     * peras. El histórico no tiene con qué compararse.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function rangoAnterior(Carbon $desde, Carbon $hasta, string $periodo): array
    {
        if ($periodo === 'todo') {
            return [null, null];
        }

        $dias = $desde->diffInDays($hasta) + 1;
        $hastaPrev = $desde->copy()->subDay();

        return [$hastaPrev->copy()->subDays($dias - 1), $hastaPrev];
    }

    private function primeraVenta(): Carbon
    {
        $fecha = $this->ventasVigentes()->min('fecha');

        return $fecha ? Carbon::parse($fecha) : Carbon::today()->startOfYear();
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    /**
     * Ventas que cuentan para el negocio.
     *
     * Mismo criterio que `ClienteController::comprasPorCliente()`, para que el
     * dashboard cuadre con lo que muestran Clientes y Cobranzas. El controlador
     * anterior filtraba `estado = 'completada'`, que sólo alcanza a 143 de 684
     * ventas y dejaba el panel en cero.
     */
    private function ventasVigentes(): Builder
    {
        return Venta::query()->where(
            fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'eliminada'])
        );
    }

    private function agregadosVentas(Carbon $desde, Carbon $hasta): object
    {
        return $this->ventasVigentes()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('COALESCE(SUM(total), 0) AS facturado')
            ->selectRaw('COALESCE(SUM(monto_pagado), 0) AS cobrado')
            ->selectRaw('COUNT(*) AS n')
            ->first();
    }

    /** Serie mensual de facturado y cobrado de los últimos N meses. */
    private function tendenciaMensual(int $meses): array
    {
        $inicio = Carbon::today()->startOfMonth()->subMonths($meses - 1);

        $filas = $this->ventasVigentes()
            ->where('fecha', '>=', $inicio->toDateString())
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') AS mes")
            ->selectRaw('COALESCE(SUM(total), 0) AS facturado')
            ->selectRaw('COALESCE(SUM(monto_pagado), 0) AS cobrado')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        // Se rellenan los meses sin ventas para que la línea no dé saltos falsos.
        $serie = [];

        for ($i = 0; $i < $meses; $i++) {
            $cursor = $inicio->copy()->addMonths($i);
            $clave = $cursor->format('Y-m');
            $fila = $filas->get($clave);

            $serie[] = [
                'mes' => $clave,
                'etiqueta' => ucfirst($cursor->translatedFormat('M y')),
                'facturado' => (float) ($fila->facturado ?? 0),
                'cobrado' => (float) ($fila->cobrado ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * Cobranzas que cuentan: las anteriores al año 2000 son basura del importador,
     * mismo criterio que `CobranzaController::vigentes()`.
     */
    private function cobranzasVigentes(): Builder
    {
        return Cobranza::query()->whereYear('fecha_emision', '>=', 2000);
    }

    /** Saldo vivo de la cartera; debe cuadrar con el total de `/admin/cobranzas`. */
    private function saldoPorCobrar(): float
    {
        return (float) $this->cobranzasVigentes()->sum('monto_pendiente');
    }

    /** Productos activos cuyo stock ya llegó (o bajó) al mínimo configurado. */
    private function productosStockBajo(): int
    {
        return Producto::query()
            ->where('estado', 'activo')
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->count();
    }

    /** Deuda pendiente repartida por días de atraso. */
    private function antiguedadDeuda(): array
    {
        $consulta = $this->cobranzasVigentes()
            ->where('estado', 'pendiente')
            ->whereRaw('YEAR(fecha_vencimiento) BETWEEN 2000 AND 2100');

        foreach (self::TRAMOS as $tramo) {
            $dias = 'DATEDIFF(CURDATE(), fecha_vencimiento)';

            $condicion = match (true) {
                $tramo['desde'] === null => "{$dias} <= {$tramo['hasta']}",
                $tramo['hasta'] === null => "{$dias} >= {$tramo['desde']}",
                default => "{$dias} BETWEEN {$tramo['desde']} AND {$tramo['hasta']}",
            };

            $consulta->selectRaw("COALESCE(SUM(CASE WHEN {$condicion} THEN monto_pendiente ELSE 0 END), 0) AS {$tramo['clave']}");
        }

        $fila = $consulta->first();

        return collect(self::TRAMOS)
            ->mapWithKeys(fn (array $t) => [$t['clave'] => (float) $fila->{$t['clave']}])
            ->all();
    }

    /** Los diez clientes con mayor saldo pendiente. */
    private function topDeudores()
    {
        return $this->cobranzasVigentes()
            ->where('estado', 'pendiente')
            ->where('monto_pendiente', '>', 0)
            ->whereNotNull('cliente_nombre')
            ->where('cliente_nombre', '!=', '')
            ->selectRaw('cliente_nombre, SUM(monto_pendiente) AS saldo, COUNT(*) AS n')
            ->groupBy('cliente_nombre')
            ->orderByDesc('saldo')
            ->limit(10)
            ->get();
    }

    /** Documentos más atrasados, para actuar sobre ellos. */
    private function cobranzasCriticas()
    {
        return $this->cobranzasVigentes()
            ->where('estado', 'pendiente')
            ->whereRaw('YEAR(fecha_vencimiento) BETWEEN 2000 AND 2100')
            ->whereRaw('DATEDIFF(CURDATE(), fecha_vencimiento) > 0')
            ->selectRaw('tipo, numero, cliente_nombre, monto_pendiente')
            ->selectRaw('DATEDIFF(CURDATE(), fecha_vencimiento) AS dias')
            ->orderByDesc('dias')
            ->limit(8)
            ->get();
    }

    /** Variación porcentual; `null` cuando no hay base con la que comparar. */
    private function variacion(float|int $actual, float|int $previo): ?float
    {
        return $previo > 0 ? round((($actual - $previo) / $previo) * 100, 1) : null;
    }
}
