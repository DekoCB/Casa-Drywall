<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\MovimientoAlmacen;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editar una Cotización o Nota de Venta ya creada ahora también permite
 * añadir o quitar productos del detalle (antes `update()` solo tocaba la
 * cabecera) — y el cambio se refleja en el comprobante impreso, porque ese
 * ya renderiza `$venta->detalles`. Boleta/Factura no pasan por acá: ya están
 * comprometidas ante SUNAT, se corrigen con una Nota de Crédito.
 */
class VentaEditarFacturaTest extends TestCase
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

    private function crearNotaConItems(Almacen $almacen, Producto $producto, int $cantidad): Venta
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-15',
            'fecha_vencimiento' => '2026-09-15',
            'tipcomp' => 'NV',
            'n_seri' => 'NV01',
            'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba',
            'precios_incluyen_igv' => 1,
            'metodo_pago' => 'Efectivo',
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => $cantidad, 'precio_unitario' => 25]],
        ]);

        return Venta::where('n_seri', 'NV01')->where('n_comp', '00000001')->firstOrFail();
    }

    private function datosEdicion(Venta $venta, array $sobrescribe = []): array
    {
        return array_merge([
            'fecha' => $venta->fecha->toDateString(),
            'fecha_vencimiento' => $venta->fecha_vencimiento->toDateString(),
            'tipcomp' => $venta->tipcomp,
            'n_seri' => $venta->n_seri,
            'n_comp' => $venta->n_comp,
            'razonsocial' => $venta->razonsocial,
            'precios_incluyen_igv' => 1,
            'metodo_pago' => $venta->metodo_pago ?? 'Efectivo',
        ], $sobrescribe);
    }

    public function test_pagina_de_edicion_muestra_404_para_boleta(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-09-15', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'razonsocial' => 'Cliente',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.edit', $venta));

        $respuesta->assertNotFound();
    }

    public function test_actualizar_muestra_404_para_boleta(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-09-15', 'fecha_vencimiento' => '2026-09-15', 'tipcomp' => '03',
            'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'razonsocial' => 'Cliente',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta));

        $respuesta->assertNotFound();
    }

    public function test_pagina_de_edicion_precarga_los_items_existentes(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $venta = $this->crearNotaConItems($almacen, $producto, 3);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.edit', $venta));

        $respuesta->assertOk();
        $respuesta->assertSee('Placa Drywall', false);
    }

    public function test_agregar_una_linea_descuenta_stock_adicional(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $venta = $this->crearNotaConItems($almacen, $producto, 3);
        $this->assertSame(7, StockAlmacen::where('producto_id', $producto->id)->value('stock'));

        // Misma línea, pero ahora pide 5 en vez de 3: la diferencia (2) es
        // lo único que debe descontarse de nuevo.
        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta, [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => 5, 'precio_unitario' => 25]],
        ]));

        $respuesta->assertRedirect();
        $this->assertSame(5, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(5, $producto->fresh()->stock);
        $this->assertSame(5, VentaDetalle::where('venta_id', $venta->id)->value('cantidad'));

        // Un solo movimiento adicional (la diferencia), no dos que se cancelan.
        $this->assertSame(2, MovimientoAlmacen::where('producto_id', $producto->id)->count());
        $ultimo = MovimientoAlmacen::where('producto_id', $producto->id)->latest('id')->first();
        $this->assertSame('salida', $ultimo->tipo);
        $this->assertSame(2, $ultimo->cantidad);
        $this->assertStringContainsString('Edición', $ultimo->motivo);
    }

    public function test_quitar_una_linea_devuelve_el_stock(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $venta = $this->crearNotaConItems($almacen, $producto, 3);
        $this->assertSame(7, StockAlmacen::where('producto_id', $producto->id)->value('stock'));

        // Se manda sin items: el producto se quitó del detalle.
        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta, [
            'tipo_operacion' => 'gravada',
            'monto' => 50,
        ]));

        $respuesta->assertRedirect();
        $this->assertSame(10, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(10, $producto->fresh()->stock);
        $this->assertSame(0, VentaDetalle::where('venta_id', $venta->id)->count());
        $this->assertNull($venta->fresh()->almacen_id);
    }

    public function test_cambiar_de_almacen_revierte_en_el_viejo_y_aplica_en_el_nuevo(): void
    {
        ['almacen' => $almacenViejo, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $almacenNuevo = Almacen::create(['nombre' => 'Secundario', 'activo' => true]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacenNuevo->id, 'stock' => 20]);

        $venta = $this->crearNotaConItems($almacenViejo, $producto, 4);
        $this->assertSame(6, StockAlmacen::where('almacen_id', $almacenViejo->id)->where('producto_id', $producto->id)->value('stock'));

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta, [
            'almacen_id' => $almacenNuevo->id,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => 4, 'precio_unitario' => 25]],
        ]));

        $respuesta->assertRedirect();
        $this->assertSame(10, StockAlmacen::where('almacen_id', $almacenViejo->id)->where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(16, StockAlmacen::where('almacen_id', $almacenNuevo->id)->where('producto_id', $producto->id)->value('stock'));
        $this->assertSame($almacenNuevo->id, $venta->fresh()->almacen_id);
    }

    public function test_cotizacion_con_items_editados_nunca_toca_stock(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-15', 'fecha_vencimiento' => '2026-09-15', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'precios_incluyen_igv' => 1,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => 3, 'precio_unitario' => 25]],
        ]);
        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta, [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => 8, 'precio_unitario' => 25]],
        ]));

        $respuesta->assertRedirect();
        $this->assertSame(10, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
        $this->assertSame(0, MovimientoAlmacen::count());
        $this->assertSame(8, VentaDetalle::where('venta_id', $venta->id)->value('cantidad'));
    }

    public function test_totales_se_recalculan_a_partir_de_los_items_nuevos(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 10);
        $venta = $this->crearNotaConItems($almacen, $producto, 2); // 2 * 25 = 50 (incluye IGV)

        $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta, [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => 4, 'precio_unitario' => 25]],
        ]));

        $venta->refresh();
        $this->assertSame(100.0, (float) $venta->total); // 4 * 25 = 100
    }

    public function test_no_bloquea_por_falta_de_stock_al_editar(): void
    {
        ['almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 3);
        $venta = $this->crearNotaConItems($almacen, $producto, 2);

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), $this->datosEdicion($venta, [
            'almacen_id' => $almacen->id,
            'items' => [['producto_codigo' => $producto->codigo, 'producto_nombre' => $producto->nombre, 'cantidad' => 10, 'precio_unitario' => 25]],
        ]));

        $respuesta->assertRedirect();
        $respuesta->assertSessionDoesntHaveErrors();
        $this->assertStringContainsString('Sin stock suficiente', session('mensaje'));
        // Ya estaba en 1 (3 - 2 vendidas al crear); la edición pide 10 en vez
        // de 2, así que se descuenta la diferencia (8) más: 1 - 8 = -7.
        $this->assertSame(-7, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
    }
}
