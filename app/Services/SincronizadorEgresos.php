<?php

namespace App\Services;

use App\Models\Egreso;
use App\Models\OrdenCompra;
use App\Models\Personal;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Genera los egresos automáticos a partir de otros módulos.
 *
 * Migrado de `administrador/egresos_sync.php`. Cada egreso automático queda
 * identificado por el par (origen, origen_id), lo que impide duplicarlo aunque
 * la sincronización se ejecute muchas veces.
 */
class SincronizadorEgresos
{
    /** Tipo de cambio de respaldo cuando la OC no trae uno propio. */
    private const TC_POR_DEFECTO = 3.35;

    /**
     * @return array<string, int> egresos creados por fuente
     */
    public function sincronizar(?int $anio = null, ?int $mes = null): array
    {
        return [
            'flete' => $this->fletes(),
            'gasolina' => $this->gasolina(),
            'promocion' => $this->promociones(),
            'compra' => $this->ordenesCompra(),
            'planilla' => $this->planilla($anio, $mes),
        ];
    }

    /** Inserta un egreso automático si no existe ya uno con el mismo origen. */
    private function insertarAuto(array $datos): bool
    {
        if ((float) $datos['monto'] <= 0) {
            return false;
        }

        $yaExiste = Egreso::where('origen', $datos['origen'])
            ->where('origen_id', $datos['origen_id'])
            ->exists();

        if ($yaExiste) {
            return false;
        }

        Egreso::create([
            'fecha' => $datos['fecha'],
            'tipo' => $datos['tipo'],
            'categoria' => $datos['categoria'] ?? 'otro',
            'descripcion' => $datos['descripcion'] ?? '',
            'monto' => (float) $datos['monto'],
            'venta_id' => $datos['venta_id'] ?? null,
            'numero_venta' => $datos['numero_venta'] ?? null,
            'usuario_id' => $datos['usuario_id'] ?? null,
            'almacen_id' => $datos['almacen_id'] ?? null,
            'origen' => $datos['origen'],
            'origen_id' => $datos['origen_id'],
        ]);

        return true;
    }

    private function fletes(): int
    {
        $creados = 0;

        foreach ($this->ventasConGastoLogistico() as $venta) {
            $creados += (int) $this->insertarAuto([
                'fecha' => $venta->fecha,
                'tipo' => 'flete',
                'categoria' => 'venta',
                'descripcion' => 'Flete '.$this->etiquetaDestino($venta),
                'monto' => $venta->costo_transporte,
                'venta_id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'usuario_id' => $venta->usuario_id,
                'almacen_id' => $venta->almacen_id,
                'origen' => 'venta_flete',
                'origen_id' => $venta->id,
            ]);
        }

        return $creados;
    }

    private function gasolina(): int
    {
        $creados = 0;

        foreach ($this->ventasConGastoLogistico() as $venta) {
            $creados += (int) $this->insertarAuto([
                'fecha' => $venta->fecha,
                'tipo' => 'gasolina',
                'categoria' => 'venta',
                'descripcion' => 'Gasolina entrega propia '.$this->etiquetaDestino($venta),
                'monto' => $venta->gasto_gasolina,
                'venta_id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'usuario_id' => $venta->usuario_id,
                'almacen_id' => $venta->almacen_id,
                'origen' => 'venta_gasolina',
                'origen_id' => $venta->id,
            ]);
        }

        return $creados;
    }

    private function promociones(): int
    {
        $ventas = Venta::where('estado', '!=', 'cancelada')
            ->where('tiene_regalo', true)
            ->where('regalo_precio', '>', 0)
            ->get(['id', 'fecha', 'numero_venta', 'regalo_precio', 'regalo_descripcion', 'almacen_id', 'usuario_id']);

        $creados = 0;

        foreach ($ventas as $venta) {
            $creados += (int) $this->insertarAuto([
                'fecha' => $venta->fecha,
                'tipo' => 'promocion',
                'categoria' => 'venta',
                'descripcion' => '🎁 Promoción: '.($venta->regalo_descripcion ?? '').' — '.($venta->numero_venta ?? ''),
                'monto' => $venta->regalo_precio,
                'venta_id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'usuario_id' => $venta->usuario_id,
                'almacen_id' => $venta->almacen_id,
                'origen' => 'venta_promo',
                'origen_id' => $venta->id,
            ]);
        }

        return $creados;
    }

    /**
     * Solo las órdenes recibidas generan egreso: una OC pendiente o cancelada
     * todavía no es un desembolso.
     */
    private function ordenesCompra(): int
    {
        $ordenes = OrdenCompra::whereRaw("LOWER(TRIM(estado)) IN ('recibido', 'recibida')")
            ->get(['id', 'numero_orden', 'fecha', 'proveedor', 'total_soles', 'total_usd', 'tc']);

        $creados = 0;

        foreach ($ordenes as $orden) {
            $monto = (float) $orden->total_soles;

            if ($monto <= 0) {
                $monto = (float) $orden->total_usd * ((float) $orden->tc ?: self::TC_POR_DEFECTO);
            }

            if ($monto <= 0) {
                continue;
            }

            $creados += (int) $this->insertarAuto([
                'fecha' => $orden->fecha ?: Carbon::today()->toDateString(),
                'tipo' => 'compra',
                'categoria' => 'orden_compra',
                'descripcion' => '📦 OC '.($orden->numero_orden ?? '').' — '.($orden->proveedor ?? 'Proveedor'),
                'monto' => $monto,
                'numero_venta' => $orden->numero_orden,
                'origen' => 'orden_compra',
                'origen_id' => $orden->id,
            ]);
        }

        return $creados;
    }

    /**
     * Un egreso por mes con la suma de sueldos del personal activo.
     * origen_id = anio*100 + mes, así el mes queda blindado contra duplicados.
     */
    private function planilla(?int $anio, ?int $mes): int
    {
        if ($anio === null || $mes === null) {
            return 0;
        }

        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        // Nunca en meses futuros, ni antes del primer colaborador, ni más de
        // 12 meses atrás: navegar al pasado no debe inventar planillas históricas.
        $primerIngreso = Personal::where('estado', 'activo')->min('fecha_ingreso');

        $esFuturo = $inicio->gt(Carbon::now()->startOfMonth());
        $antesDeTodo = $primerIngreso && $fin->lt(Carbon::parse($primerIngreso));
        $muyAntiguo = $inicio->lt(Carbon::now()->startOfMonth()->subMonths(12));

        if ($esFuturo || $antesDeTodo || $muyAntiguo) {
            return 0;
        }

        $planilla = Personal::where('estado', 'activo')
            ->where(fn ($q) => $q->whereNull('fecha_ingreso')->orWhere('fecha_ingreso', '<=', $fin->toDateString()))
            ->selectRaw('COALESCE(SUM(sueldo), 0) AS total, COUNT(*) AS n')
            ->first();

        if ((float) $planilla->total <= 0) {
            return 0;
        }

        // Mes en curso: la planilla se fecha hoy; meses cerrados: último día.
        $fechaPlanilla = $inicio->isSameMonth(Carbon::now())
            ? Carbon::today()->toDateString()
            : $fin->toDateString();

        return (int) $this->insertarAuto([
            'fecha' => $fechaPlanilla,
            'tipo' => 'planilla',
            'categoria' => 'planilla',
            'descripcion' => '👥 Planilla mensual — '.(int) $planilla->n.' colaborador(es) activos',
            'monto' => $planilla->total,
            'origen' => 'planilla',
            'origen_id' => $anio * 100 + $mes,
        ]);
    }

    private function ventasConGastoLogistico()
    {
        return Venta::where('estado', '!=', 'cancelada')
            ->where(fn ($q) => $q->where('costo_transporte', '>', 0)->orWhere('gasto_gasolina', '>', 0))
            ->get(['id', 'fecha', 'numero_venta', 'costo_transporte', 'gasto_gasolina', 'destino_entrega', 'almacen_id', 'usuario_id']);
    }

    private function etiquetaDestino(Venta $venta): string
    {
        return trim(($venta->destino_entrega ?? '').' — '.($venta->numero_venta ?? ''), ' —');
    }
}
