<?php

namespace App\Services;

use App\Models\Cobranza;
use App\Models\NotificacionLectura;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Notificaciones de la campanita del topbar. No hay una tabla de eventos:
 * cada categoría se recalcula en vivo a partir de lo que ya existe en el
 * sistema (stock, cobranzas vencidas, comprobantes no enviados a SUNAT), y
 * lo único que se guarda por usuario es qué `clave` ya marcó como leída
 * (`notificacion_lecturas`). Si el mismo hecho vuelve a ocurrir más tarde
 * (p.ej. un producto se agota, se repone y se vuelve a agotar) reaparece
 * como no leído: la clave es estable por registro, no por evento.
 */
class CentroNotificaciones
{
    private const TOPE_POR_CATEGORIA = 20;

    /** @return array{porCategoria: array<string, Collection>, todas: Collection, noLeidas: int} */
    public function paraUsuario(Usuario $usuario): array
    {
        $porCategoria = [
            'comprobantes' => $this->comprobantes(),
            'pagos'        => $this->pagos(),
            'inventario'   => $this->inventario(),
        ];

        $todas = collect($porCategoria)->flatten(1)->sortByDesc('fecha')->values();

        $leidas = NotificacionLectura::where('usuario_id', $usuario->id)
            ->pluck('clave')->flip();

        $todas = $todas->map(function (array $n) use ($leidas) {
            $n['leido'] = $leidas->has($n['clave']);

            return $n;
        });

        $porCategoria = collect($porCategoria)->map(
            fn (Collection $items) => $items->map(fn (array $n) => $n + ['leido' => $leidas->has($n['clave'])])
        )->all();

        return [
            'todas' => $todas,
            'porCategoria' => $porCategoria,
            'noLeidas' => $todas->where('leido', false)->count(),
        ];
    }

    /** Marca como leídas todas las notificaciones vigentes en este momento para el usuario. */
    public function marcarTodasLeidas(Usuario $usuario): void
    {
        $claves = collect([$this->comprobantes(), $this->pagos(), $this->inventario()])
            ->flatten(1)
            ->pluck('clave');

        $filas = $claves->map(fn (string $clave) => [
            'usuario_id' => $usuario->id,
            'clave' => $clave,
            'leido_en' => now(),
        ])->all();

        if ($filas !== []) {
            NotificacionLectura::query()->upsert($filas, ['usuario_id', 'clave'], ['leido_en']);
        }
    }

    /** Boletas/Facturas que nunca se enviaron a SUNAT o que fueron rechazadas. */
    private function comprobantes(): Collection
    {
        return Venta::whereIn('tipcomp', ['01', '03'])
            ->whereIn('estado_factura', ['pendiente', 'rechazado'])
            ->where(fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'eliminada']))
            ->orderByDesc('fecha')
            ->limit(self::TOPE_POR_CATEGORIA)
            ->get()
            ->map(fn (Venta $v) => [
                'clave' => "comprobantes:{$v->id}",
                'categoria' => 'comprobantes',
                'titulo' => $v->estado_factura === 'rechazado' ? 'Comprobante rechazado por SUNAT' : 'Comprobante no enviado a SUNAT',
                'detalle' => "{$v->n_seri}-{$v->n_comp} — ".($v->razonsocial ?: $v->cliente_nombre ?: 'Cliente Varios'),
                'fecha' => Carbon::parse($v->fecha ?? now()),
                'url' => route('admin.ventas.comprobante', $v),
            ]);
    }

    /**
     * Cobranzas pendientes con 90 días o más de atraso — mismo umbral que
     * Cobranzas. Se filtra en PHP (no con `whereRaw`/`DATEDIFF`) para no
     * atarse a funciones específicas de MySQL.
     */
    private function pagos(): Collection
    {
        return Cobranza::pendientes()
            ->whereNotNull('fecha_vencimiento')
            ->orderBy('fecha_vencimiento')
            ->get()
            ->filter(fn (Cobranza $c) => ! $c->vencimientoInvalido() && ($c->diasVencidos() ?? 0) >= 90)
            ->take(self::TOPE_POR_CATEGORIA)
            ->map(fn (Cobranza $c) => [
                'clave' => "pagos:{$c->id}",
                'categoria' => 'pagos',
                'titulo' => 'Cobranza vencida',
                'detalle' => "{$c->cliente_nombre} — {$c->diasVencidos()} días vencido",
                'fecha' => Carbon::parse($c->fecha_vencimiento ?? now()),
                'url' => route('admin.cobranzas.index', ['q' => $c->numero]),
            ]);
    }

    /**
     * Productos activos sin stock. `productos` no tiene `updated_at`, así que
     * la fecha se toma del último movimiento de stock por almacén (cuándo
     * realmente cambió el stock); si nunca tuvo uno, cae a `created_at`.
     */
    private function inventario(): Collection
    {
        return Producto::activos()
            ->where('stock', '<=', 0)
            ->withMax('stockPorAlmacen', 'updated_at')
            ->orderByDesc('stock_por_almacen_max_updated_at')
            ->limit(self::TOPE_POR_CATEGORIA)
            ->get()
            ->map(fn (Producto $p) => [
                'clave' => "inventario:{$p->id}",
                'categoria' => 'inventario',
                'titulo' => 'Producto agotado',
                'detalle' => $p->nombre,
                'fecha' => Carbon::parse($p->stock_por_almacen_max_updated_at ?? $p->created_at ?? now()),
                'url' => route('admin.productos.index', ['q' => $p->codigo]),
            ]);
    }
}
