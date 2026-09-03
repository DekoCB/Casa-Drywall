<?php

namespace App\Services\Pos;

use App\Http\Controllers\Admin\VentaController;
use App\Models\Cliente;
use App\Models\MovimientoAlmacen;
use App\Models\MovimientoCaja;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Models\VentaSuspendida;
use App\Services\GeneradorCorrelativo;
use App\Services\PrecioCalculador;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dueño de la transacción completa de una venta del Punto de Venta: caja,
 * stock, precios/IGV, correlativo y pagos. La emisión electrónica (API-GO)
 * se dispara desde el controller, después de que esto haya confirmado
 * (mismo patrón que `VentaController::storeFactura()`), para que una caída
 * de SUNAT nunca bloquee ni revierta la venta.
 */
class PosVentaService
{
    private const TIPOS_PERMITIDOS = ['03', '01', 'NV'];

    public function __construct(
        private readonly GeneradorCorrelativo $correlativo,
        private readonly PrecioCalculador $precios,
        private readonly CajaService $cajas,
    ) {}

    public function procesar(array $datos, Usuario $usuario): Venta
    {
        // Idempotencia: un reintento del mismo clic (doble clic, red lenta)
        // no debe crear una segunda venta.
        $token = trim((string) ($datos['pos_token'] ?? ''));

        if ($token !== '' && $existente = Venta::where('pos_token', $token)->first()) {
            return $existente;
        }

        $sesion = $this->cajas->sesionAbiertaDe($usuario);

        if (! $sesion) {
            throw ValidationException::withMessages([
                'caja' => 'No tienes una caja abierta. Ábrela antes de cobrar.',
            ]);
        }

        $almacenId = (int) ($datos['almacen_id'] ?? 0);

        if ($almacenId <= 0) {
            throw ValidationException::withMessages([
                'almacen_id' => 'Selecciona un almacén.',
            ]);
        }

        $tipcomp = (string) ($datos['tipcomp'] ?? '');

        if (! in_array($tipcomp, self::TIPOS_PERMITIDOS, true)) {
            throw ValidationException::withMessages([
                'tipcomp' => 'Tipo de comprobante no válido para el Punto de Venta.',
            ]);
        }

        $items = $this->itemsValidos($datos['items'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Agrega al menos un producto.',
            ]);
        }

        $pagos = $this->pagosValidos($datos['pagos'] ?? []);

        if ($pagos === []) {
            throw ValidationException::withMessages([
                'pagos' => 'Registra al menos un método de pago.',
            ]);
        }

        $descuentoPct = $this->descuentoAutorizado($datos, $usuario);

        return DB::transaction(function () use ($datos, $items, $pagos, $almacenId, $tipcomp, $descuentoPct, $sesion, $usuario, $token) {
            // 1. Lock de stock: una sola query, orden fijo por producto_id
            //    para que dos cobros concurrentes que comparten productos no
            //    se bloqueen en cruz (deadlock).
            $stockFilas = StockAlmacen::where('almacen_id', $almacenId)
                ->whereIn('producto_id', array_column($items, 'producto_id'))
                ->orderBy('producto_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('producto_id');

            foreach ($items as $item) {
                $disponible = (int) ($stockFilas->get($item['producto_id'])?->stock ?? 0);

                if ($disponible < $item['cantidad']) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para {$item['nombre']}: quedan {$disponible}.",
                    ]);
                }
            }

            // 2. Precios/IGV recalculados en servidor — nunca se confía en
            //    el total que mandó el JS.
            $lineas = [];
            $subtotalBruto = 0.0;
            $descuentoAcum = 0.0;

            foreach ($items as $item) {
                $calculo = $this->precios->calcularLinea($item['cantidad'], $item['precio_unitario'], $descuentoPct);
                $lineas[] = $item + $calculo;
                $subtotalBruto += $calculo['neto'];
                $descuentoAcum += $calculo['descuento'];
            }

            // Los precios del catálogo ya incluyen IGV (precio de venta al público).
            $desglose = $this->precios->desglosarImporte($subtotalBruto, true);
            $total = round($desglose['base'] + $desglose['igv'], 2);

            $montoPagado = round(array_sum(array_column($pagos, 'monto')), 2);

            if ($montoPagado < $total - 0.01) {
                throw ValidationException::withMessages([
                    'pagos' => 'El monto pagado es menor al total.',
                ]);
            }

            $vuelto = round($montoPagado - $total, 2);

            // 3. Correlativo — dentro de la transacción, con el lock que ya
            //    protege esta misma consulta contra otro cajero concurrente.
            $serie = VentaController::TIPOS[$tipcomp]['serie'];
            $nComp = $this->correlativo->documentoInterno($tipcomp, $serie);

            $cliente = ! empty($datos['cliente_id']) ? Cliente::find($datos['cliente_id']) : null;
            $metodoPago = count($pagos) === 1 ? $pagos[0]['metodo_pago'] : 'Mixto';

            // 4. Venta + detalle + pagos.
            $venta = Venta::create([
                'numero_venta' => $this->correlativo->venta(),
                'tipcomp' => $tipcomp,
                'tipo_comprobante' => VentaController::TIPOS[$tipcomp]['nombre'],
                'n_seri' => $serie,
                'n_comp' => $nComp,
                'n_ruc' => $datos['n_ruc'] ?? '',
                'razonsocial' => $datos['razonsocial'] ?? 'Cliente Varios',
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $cliente?->nombres ?? $datos['razonsocial'] ?? 'Cliente Varios',
                'cliente_ruc' => $datos['n_ruc'] ?? null,
                'cliente_direccion' => $cliente?->direccion,
                'cliente_telefono' => $cliente?->telefono,
                'cliente_correo' => $cliente?->email,
                'cliente_distrito' => $cliente?->distrito,
                'condicion_pago' => 'Contado',
                'fecha' => now()->toDateString(),
                'fecha_vencimiento' => now()->toDateString(),
                'baseimp' => $desglose['base'],
                'subtotal' => $desglose['base'],
                'igv' => $desglose['igv'],
                'exonerado' => 0,
                'inafecto' => 0,
                'total' => $total,
                'moneda' => 'PEN',
                'tipo_cambio' => 1,
                'tipcambio' => 1,
                'usuario_id' => $usuario->id,
                'almacen_id' => $almacenId,
                'estado' => 'activa',
                'estado_cobro' => 'pagada',
                'monto_pagado' => $total,
                'monto_pendiente' => 0,
                'fecha_pago' => now()->toDateString(),
                'metodo_pago' => $metodoPago,
                'sesion_caja_id' => $sesion->id,
                'canal' => 'pos',
                'vuelto' => $vuelto,
                'descuento_total' => round($descuentoAcum, 2),
                'pos_token' => $token !== '' ? $token : null,
            ]);

            foreach ($lineas as $linea) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $linea['producto_id'],
                    'prod_codigo' => $linea['codigo'],
                    'prod_nombre' => $linea['nombre'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento_pct' => $descuentoPct > 0 ? $descuentoPct : null,
                    'subtotal' => $linea['neto'],
                ]);
            }

            foreach ($pagos as $pago) {
                VentaPago::create([
                    'venta_id' => $venta->id,
                    'metodo_pago' => $pago['metodo_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?: null,
                ]);
            }

            // 5. Stock — descuento bajo el mismo lock tomado en el paso 1.
            foreach ($items as $item) {
                $fila = $stockFilas[$item['producto_id']];
                $anterior = (int) $fila->stock;
                $nuevo = $anterior - $item['cantidad'];

                $fila->update(['stock' => $nuevo]);

                MovimientoAlmacen::create([
                    'producto_id' => $item['producto_id'],
                    'almacen_id' => $almacenId,
                    'tipo' => 'salida',
                    'cantidad' => $item['cantidad'],
                    'stock_anterior' => $anterior,
                    'stock_nuevo' => $nuevo,
                    'motivo' => "Venta POS {$venta->numero_venta}",
                    'referencia' => $venta->numero_venta,
                    'usuario_id' => $usuario->id,
                ]);

                Producto::find($item['producto_id'])?->recalcularStock();
            }

            // 6. Movimientos de caja: uno por pago, y uno negativo por el
            //    vuelto entregado en efectivo.
            foreach ($pagos as $pago) {
                MovimientoCaja::create([
                    'sesion_caja_id' => $sesion->id,
                    'tipo' => 'venta',
                    'metodo_pago' => $pago['metodo_pago'],
                    'monto' => $pago['monto'],
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $venta->id,
                    'usuario_id' => $usuario->id,
                ]);
            }

            if ($vuelto > 0) {
                MovimientoCaja::create([
                    'sesion_caja_id' => $sesion->id,
                    'tipo' => 'venta',
                    'metodo_pago' => 'Efectivo',
                    'monto' => -$vuelto,
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $venta->id,
                    'descripcion' => "Vuelto venta {$venta->numero_venta}",
                    'usuario_id' => $usuario->id,
                ]);
            }

            // 7. Si la venta viene de un carrito suspendido, se confirma y se borra.
            if (! empty($datos['venta_suspendida_id'])) {
                VentaSuspendida::where('id', $datos['venta_suspendida_id'])->delete();
            }

            return $venta;
        }, 3);
    }

    public function suspender(array $datos, Usuario $usuario): VentaSuspendida
    {
        $items = $this->itemsValidos($datos['items'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'No hay nada que suspender: el carrito está vacío.',
            ]);
        }

        $total = collect($items)->sum(fn (array $i) => $i['cantidad'] * $i['precio_unitario']);

        return VentaSuspendida::create([
            'usuario_id' => $usuario->id,
            'cliente_etiqueta' => $datos['razonsocial'] ?? 'Cliente Varios',
            'total_referencial' => round($total, 2),
            'datos' => $datos,
        ]);
    }

    /**
     * Reconstruye cada línea a partir del producto real en catálogo — nunca
     * se confía en el nombre/precio que mande el cliente, solo en el id y
     * la cantidad pedida.
     */
    private function itemsValidos(array $items): array
    {
        $porProducto = [];

        foreach ($items as $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);

            if ($productoId <= 0 || $cantidad <= 0) {
                continue;
            }

            // Si el mismo producto aparece dos veces en el carrito, se suma
            // (evita dos líneas de stock separadas para el mismo producto,
            // lo que además rompería la key única del lock por producto_id).
            $porProducto[$productoId] = ($porProducto[$productoId] ?? 0) + $cantidad;
        }

        if ($porProducto === []) {
            return [];
        }

        $productos = Producto::activos()->whereIn('id', array_keys($porProducto))->get()->keyBy('id');

        $validos = [];

        foreach ($porProducto as $productoId => $cantidad) {
            $producto = $productos->get($productoId);

            if (! $producto) {
                continue;
            }

            $validos[] = [
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $producto->precio_venta,
            ];
        }

        return $validos;
    }

    private function pagosValidos(array $pagos): array
    {
        $validos = [];

        foreach ($pagos as $pago) {
            $monto = round((float) ($pago['monto'] ?? 0), 2);
            $metodo = trim((string) ($pago['metodo_pago'] ?? ''));

            if ($metodo === '' || $monto <= 0) {
                continue;
            }

            $validos[] = [
                'metodo_pago' => $metodo,
                'monto' => $monto,
                'referencia' => trim((string) ($pago['referencia'] ?? '')),
            ];
        }

        return $validos;
    }

    /** Solo admin aplica descuento; si un no-admin lo manda, se ignora. */
    private function descuentoAutorizado(array $datos, Usuario $usuario): float
    {
        if (! $usuario->esAdmin()) {
            return 0.0;
        }

        $pct = (float) ($datos['descuento_pct'] ?? 0);

        return max(0.0, min(100.0, round($pct, 2)));
    }
}
