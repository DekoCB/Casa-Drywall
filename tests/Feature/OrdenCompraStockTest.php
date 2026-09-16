<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\MovimientoAlmacen;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hasta ahora una Orden de Compra no tocaba el Inventario: `productos` era
 * pura foto en JSON. El negocio pidió que registrar una compra sume el
 * stock automático — para eso la orden ahora elige un almacén destino, y
 * cada línea que matchea un Producto real (por código) suma esa cantidad.
 * Una línea manual (código que no está en el catálogo) no toca stock, igual
 * que ya pasa en Ventas con las líneas sin producto real.
 */
class OrdenCompraStockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosBase(array $sobrescribe = []): array
    {
        return array_merge([
            'estado' => 'Pendiente', 'gasto_unit' => '0', 'condicion_pago' => 'contado', 'tc' => '1',
            'proveedor' => 'Distribuidora Drywall SAC', 'ruc' => '20123456789',
            'fecha' => now()->toDateString(), 'precio_venta' => '0',
        ], $sobrescribe);
    }

    public function test_registrar_una_orden_suma_stock_al_almacen_elegido(): void
    {
        $almacen = Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]);
        $producto = Producto::create(['codigo' => 'DRY-001', 'nombre' => 'Placa Drywall', 'estado' => 'activo', 'precio_compra' => 10, 'stock' => 0]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-001', 'almacen_id' => $almacen->id,
            'total_usd' => '200', 'total_soles' => '200',
            'productos' => json_encode([
                ['codigo' => 'DRY-001', 'descripcion' => 'Placa Drywall', 'precio_unit_usd' => 10, 'cantidad' => 20],
            ]),
        ]))->assertRedirect();

        $this->assertSame(20, StockAlmacen::where('producto_id', $producto->id)->where('almacen_id', $almacen->id)->value('stock'));
        $this->assertSame(20, $producto->fresh()->stock);

        $orden = OrdenCompra::where('numero_orden', 'OC-STK-001')->firstOrFail();
        $mov = MovimientoAlmacen::where('orden_compra_id', $orden->id)->firstOrFail();
        $this->assertSame('entrada', $mov->tipo);
        $this->assertSame(20, $mov->cantidad);
        $this->assertSame($producto->id, $mov->producto_id);
    }

    public function test_editar_la_orden_ajusta_el_stock_a_la_nueva_cantidad(): void
    {
        $almacen = Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]);
        $producto = Producto::create(['codigo' => 'DRY-002', 'nombre' => 'Perfil', 'estado' => 'activo', 'precio_compra' => 5, 'stock' => 0]);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-002', 'almacen_id' => $almacen->id,
            'total_usd' => '50', 'total_soles' => '50',
            'productos' => json_encode([
                ['codigo' => 'DRY-002', 'descripcion' => 'Perfil', 'precio_unit_usd' => 5, 'cantidad' => 10],
            ]),
        ]));
        $this->assertSame(10, $producto->fresh()->stock);

        $orden = OrdenCompra::where('numero_orden', 'OC-STK-002')->firstOrFail();

        // Se corrige: en realidad eran 15, no 10.
        $this->actingAs($admin, 'web')->put(route('admin.ordenes-compra.update', $orden), [
            'estado' => 'Pendiente', 'proveedor' => 'Distribuidora Drywall SAC', 'almacen_id' => $almacen->id,
            'numero_orden' => 'OC-STK-002', 'fecha' => now()->toDateString(),
            'productos' => [
                ['codigo' => 'DRY-002', 'descripcion' => 'Perfil', 'cantidad' => 15, 'precio' => 5],
            ],
            'total_usd' => '75', 'total_soles' => '75',
        ])->assertRedirect();

        $this->assertSame(15, $producto->fresh()->stock);
        $this->assertSame(1, MovimientoAlmacen::where('orden_compra_id', $orden->id)->count());
    }

    public function test_quitar_una_linea_al_editar_revierte_su_stock(): void
    {
        $almacen = Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]);
        $p1 = Producto::create(['codigo' => 'DRY-003', 'nombre' => 'Tornillo', 'estado' => 'activo', 'precio_compra' => 1, 'stock' => 0]);
        $p2 = Producto::create(['codigo' => 'DRY-004', 'nombre' => 'Cinta', 'estado' => 'activo', 'precio_compra' => 2, 'stock' => 0]);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-003', 'almacen_id' => $almacen->id,
            'total_usd' => '100', 'total_soles' => '100',
            'productos' => json_encode([
                ['codigo' => 'DRY-003', 'descripcion' => 'Tornillo', 'precio_unit_usd' => 1, 'cantidad' => 100],
                ['codigo' => 'DRY-004', 'descripcion' => 'Cinta', 'precio_unit_usd' => 2, 'cantidad' => 30],
            ]),
        ]));
        $this->assertSame(100, $p1->fresh()->stock);
        $this->assertSame(30, $p2->fresh()->stock);

        $orden = OrdenCompra::where('numero_orden', 'OC-STK-003')->firstOrFail();

        // Se quita la línea de Cinta: su stock debe volver a 0.
        $this->actingAs($admin, 'web')->put(route('admin.ordenes-compra.update', $orden), [
            'estado' => 'Pendiente', 'proveedor' => 'Distribuidora Drywall SAC', 'almacen_id' => $almacen->id,
            'numero_orden' => 'OC-STK-003', 'fecha' => now()->toDateString(),
            'productos' => [
                ['codigo' => 'DRY-003', 'descripcion' => 'Tornillo', 'cantidad' => 100, 'precio' => 1],
            ],
            'total_usd' => '100', 'total_soles' => '100',
        ]);

        $this->assertSame(100, $p1->fresh()->stock);
        $this->assertSame(0, $p2->fresh()->stock);
    }

    public function test_linea_manual_sin_producto_en_el_catalogo_no_toca_stock(): void
    {
        $almacen = Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-004', 'almacen_id' => $almacen->id,
            'total_usd' => '30', 'total_soles' => '30',
            'productos' => json_encode([
                ['codigo' => 'NO-EXISTE', 'descripcion' => 'Producto no catalogado', 'precio_unit_usd' => 3, 'cantidad' => 10],
            ]),
        ]))->assertRedirect();

        $this->assertSame(0, MovimientoAlmacen::count());
        $this->assertSame(0, StockAlmacen::count());
    }

    public function test_sin_almacen_elegido_no_suma_stock_ni_falla(): void
    {
        $producto = Producto::create(['codigo' => 'DRY-005', 'nombre' => 'Masilla', 'estado' => 'activo', 'precio_compra' => 8, 'stock' => 0]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-005',
            'total_usd' => '80', 'total_soles' => '80',
            'productos' => json_encode([
                ['codigo' => 'DRY-005', 'descripcion' => 'Masilla', 'precio_unit_usd' => 8, 'cantidad' => 10],
            ]),
        ]))->assertRedirect();

        $this->assertSame(0, $producto->fresh()->stock);
        $this->assertSame(0, MovimientoAlmacen::count());
    }

    public function test_orden_cancelada_no_suma_stock(): void
    {
        $almacen = Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]);
        $producto = Producto::create(['codigo' => 'DRY-006', 'nombre' => 'Angular', 'estado' => 'activo', 'precio_compra' => 4, 'stock' => 0]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-006', 'almacen_id' => $almacen->id, 'estado' => 'Cancelado',
            'total_usd' => '40', 'total_soles' => '40',
            'productos' => json_encode([
                ['codigo' => 'DRY-006', 'descripcion' => 'Angular', 'precio_unit_usd' => 4, 'cantidad' => 10],
            ]),
        ]))->assertRedirect();

        $this->assertSame(0, $producto->fresh()->stock);
    }

    public function test_borrar_la_orden_revierte_el_stock_que_habia_sumado(): void
    {
        $almacen = Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]);
        $producto = Producto::create(['codigo' => 'DRY-007', 'nombre' => 'Riel', 'estado' => 'activo', 'precio_compra' => 6, 'stock' => 0]);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post(route('admin.ordenes-compra.store'), $this->datosBase([
            'numero_orden' => 'OC-STK-007', 'almacen_id' => $almacen->id,
            'total_usd' => '60', 'total_soles' => '60',
            'productos' => json_encode([
                ['codigo' => 'DRY-007', 'descripcion' => 'Riel', 'precio_unit_usd' => 6, 'cantidad' => 10],
            ]),
        ]));
        $this->assertSame(10, $producto->fresh()->stock);

        $orden = OrdenCompra::where('numero_orden', 'OC-STK-007')->firstOrFail();
        $this->actingAs($admin, 'web')->delete(route('admin.ordenes-compra.destroy', $orden))->assertRedirect();

        $this->assertSame(0, $producto->fresh()->stock);
        $this->assertSame(0, MovimientoAlmacen::count());
    }
}
