<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Cobranza;
use App\Models\MovimientoAlmacen;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `storeFactura()` (el alta de "Nueva Venta", distinta del POS) ahora
 * también descuenta stock y crea movimiento cuando una línea enlaza un
 * producto real del catálogo — mismo patrón que ya usaba `PosVentaService`,
 * para que Inventario/Movimientos por fin cuadren con las ventas normales.
 */
class VentaFacturaStockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function crearEscenario(int $stock = 10): array
    {
        $almacen = Almacen::create(['nombre' => 'Principal', 'activo' => true]);
        $producto = Producto::create(['nombre' => 'Placa Drywall 1/2"', 'codigo' => 'DRY-001', 'precio_venta' => 25]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => $stock]);

        return compact('almacen', 'producto');
    }

    private function datosBase(): array
    {
        return [
            'fecha' => '2026-09-15',
            'fecha_vencimiento' => '2026-09-15',
            'tipcomp' => 'NV',
            'n_seri' => 'NV01',
            'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba',
            'precios_incluyen_igv' => 1,
        ];
    }

    public function test_venta_con_stock_suficiente_descuenta_y_crea_movimiento(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 3, 'precio_unitario' => 25]],
        ]);

        $respuesta->assertRedirect();

        $venta = Venta::where('n_seri', 'NV01')->where('n_comp', '00000001')->firstOrFail();
        $detalle = VentaDetalle::where('venta_id', $venta->id)->firstOrFail();
        $this->assertSame($producto->id, $detalle->producto_id);
        $this->assertSame($almacen->id, $venta->almacen_id);

        $this->assertSame(7, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(7, $producto->fresh()->stock);

        $movimiento = MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'salida')->firstOrFail();
        $this->assertSame($venta->numero_venta, $movimiento->referencia);
        $this->assertStringContainsString($venta->numero_venta, $movimiento->motivo);
    }

    public function test_stock_insuficiente_no_escribe_nada(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 1);

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 5, 'precio_unitario' => 25]],
        ]);

        $respuesta->assertSessionHasErrors('items');

        $this->assertSame(0, Venta::count());
        $this->assertSame(0, Cobranza::count());
        $this->assertSame(0, VentaDetalle::count());
        $this->assertSame(0, MovimientoAlmacen::count());
        $this->assertSame(1, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
    }

    public function test_dos_lineas_del_mismo_producto_verifican_demanda_acumulada(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 5);

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'almacen_id' => $almacen->id,
            'items' => [
                ['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 3, 'precio_unitario' => 25],
                ['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 3, 'precio_unitario' => 25],
            ],
        ]);

        $respuesta->assertSessionHasErrors('items');
        $this->assertSame(0, Venta::count());
        $this->assertSame(5, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
    }

    public function test_linea_manual_sin_producto_no_requiere_almacen_ni_toca_stock(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'items' => [['producto_codigo' => '', 'producto_nombre' => 'Servicio de instalación', 'cantidad' => 1, 'precio_unitario' => 100]],
        ]);

        $respuesta->assertRedirect();

        $venta = Venta::where('n_seri', 'NV01')->where('n_comp', '00000001')->firstOrFail();
        $detalle = VentaDetalle::where('venta_id', $venta->id)->firstOrFail();
        $this->assertNull($detalle->producto_id);
        $this->assertNull($venta->almacen_id);
        $this->assertSame(0, MovimientoAlmacen::count());
    }

    public function test_cotizacion_con_items_de_catalogo_no_descuenta_stock(): void
    {
        ['producto' => $producto] = $this->crearEscenario(stock: 10);

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-15',
            'fecha_vencimiento' => '2026-09-15',
            'tipcomp' => 'COT',
            'n_seri' => 'CT01',
            'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba',
            'precios_incluyen_igv' => 1,
            'items' => [['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 3, 'precio_unitario' => 25]],
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionDoesntHaveErrors();

        $this->assertSame(10, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(0, MovimientoAlmacen::count());
    }

    public function test_falta_almacen_con_producto_real_es_rechazado(): void
    {
        $this->crearEscenario(stock: 10);

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'items' => [['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 1, 'precio_unitario' => 25]],
        ]);

        $respuesta->assertSessionHasErrors('almacen_id');
        $this->assertSame(0, Venta::count());
    }

    public function test_anular_restaura_stock_y_crea_movimiento_de_entrada(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 4, 'precio_unitario' => 25]],
        ]);

        $venta = Venta::where('n_seri', 'NV01')->where('n_comp', '00000001')->firstOrFail();
        $this->assertSame(6, StockAlmacen::where('producto_id', $producto->id)->value('stock'));

        $this->actingAs($admin, 'web')->post(route('admin.ventas.anular', $venta))->assertRedirect();

        $this->assertSame(10, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(10, $producto->fresh()->stock);
        $this->assertSame('cancelada', $venta->fresh()->estado);

        $entrada = MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'entrada')->firstOrFail();
        $this->assertStringContainsString('Anulación', $entrada->motivo);
    }

    public function test_anular_dos_veces_no_duplica_la_restauracion(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => 'DRY-001', 'producto_nombre' => 'Placa Drywall 1/2"', 'cantidad' => 4, 'precio_unitario' => 25]],
        ]);
        $venta = Venta::where('n_seri', 'NV01')->where('n_comp', '00000001')->firstOrFail();

        $this->actingAs($admin, 'web')->post(route('admin.ventas.anular', $venta))->assertRedirect();
        $this->assertSame(10, StockAlmacen::where('producto_id', $producto->id)->value('stock'));

        $segunda = $this->actingAs($admin, 'web')->post(route('admin.ventas.anular', $venta));
        $segunda->assertRedirect();
        $this->assertStringContainsString('ya fue anulado', session('error'));

        $this->assertSame(10, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(1, MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'entrada')->count());
    }

    public function test_anular_venta_historica_sin_almacen_no_falla(): void
    {
        $admin = $this->admin();
        $producto = Producto::create(['nombre' => 'Producto Histórico', 'codigo' => 'HIST-001', 'precio_venta' => 10]);

        $venta = Venta::create([
            'fecha' => '2026-01-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000099',
            'estado' => 'activa',
        ]);
        VentaDetalle::create([
            'venta_id' => $venta->id, 'producto_id' => $producto->id, 'prod_nombre' => 'Producto Histórico',
            'cantidad' => 2, 'precio_unitario' => 10, 'subtotal' => 20,
        ]);

        $respuesta = $this->actingAs($admin, 'web')->post(route('admin.ventas.anular', $venta));

        $respuesta->assertRedirect();
        $this->assertSame('cancelada', $venta->fresh()->estado);
        $this->assertSame(0, StockAlmacen::count());
        $this->assertSame(0, MovimientoAlmacen::count());
    }
}
