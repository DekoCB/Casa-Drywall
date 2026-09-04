<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\MovimientoAlmacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo Inventario: listado de movimientos, traslados entre almacenes,
 * devoluciones a proveedor, y los reportes Kardex/Kardex valorizado/
 * Reporte de Inventario.
 */
class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function dosAlmacenes(): array
    {
        return [
            Almacen::create(['nombre' => 'Almacén 1', 'activo' => true]),
            Almacen::create(['nombre' => 'Almacén 2', 'activo' => true]),
        ];
    }

    public function test_traslado_mueve_stock_entre_almacenes_y_enlaza_los_dos_movimientos(): void
    {
        [$origen, $destino] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P001', 'nombre' => 'Placa Drywall', 'stock' => 50, 'precio_compra' => 20]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $origen->id, 'stock' => 50]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.inventario.traslados.store'), [
            'producto_id' => $producto->id,
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'cantidad' => 20,
        ])->assertRedirect();

        $this->assertSame(30, StockAlmacen::where('almacen_id', $origen->id)->value('stock'));
        $this->assertSame(20, StockAlmacen::where('almacen_id', $destino->id)->value('stock'));

        $movs = MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'traslado')->get();
        $this->assertCount(2, $movs);
        $this->assertSame($movs[0]->referencia, $movs[1]->referencia);
    }

    public function test_traslado_rechaza_cuando_no_hay_stock_suficiente_en_origen(): void
    {
        [$origen, $destino] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P002', 'nombre' => 'Perfil', 'stock' => 5]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $origen->id, 'stock' => 5]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.inventario.traslados.store'), [
            'producto_id' => $producto->id,
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'cantidad' => 50,
        ])->assertSessionHasErrors('cantidad');

        $this->assertSame(5, StockAlmacen::where('almacen_id', $origen->id)->value('stock'));
        $this->assertSame(0, MovimientoAlmacen::count());
    }

    public function test_devolucion_descuenta_stock_y_anota_el_proveedor(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P003', 'nombre' => 'Masilla', 'stock' => 30]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 30]);
        $proveedor = Proveedor::create(['ruc' => '20123456789', 'razon_social' => 'Distribuidora XYZ']);

        $this->actingAs($this->admin(), 'web')->post(route('admin.inventario.devoluciones.store'), [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'proveedor_id' => $proveedor->id,
            'cantidad' => 10,
            'motivo' => 'Producto defectuoso',
        ])->assertRedirect();

        $this->assertSame(20, StockAlmacen::where('almacen_id', $almacen->id)->value('stock'));

        $mov = MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'devolucion')->firstOrFail();
        $this->assertStringContainsString('Distribuidora XYZ', $mov->referencia);
    }

    public function test_listado_de_movimientos_filtra_por_tipo(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P004', 'nombre' => 'Tornillo', 'stock' => 100]);

        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 100, 'stock_anterior' => 0, 'stock_nuevo' => 100]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'ajuste', 'cantidad' => 90, 'stock_anterior' => 100, 'stock_nuevo' => 90]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.movimientos', ['tipo' => 'ajuste']));

        $respuesta->assertOk();
        $tipos = $respuesta->viewData('movimientos')->pluck('tipo');
        $this->assertSame(['ajuste'], $tipos->unique()->values()->all());
    }

    public function test_kardex_muestra_el_saldo_corrido_ya_guardado_por_movimiento(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P005', 'nombre' => 'Cinta', 'stock' => 40, 'precio_compra' => 5]);

        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 50, 'stock_anterior' => 0, 'stock_nuevo' => 50]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'salida', 'cantidad' => 10, 'stock_anterior' => 50, 'stock_nuevo' => 40]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.kardex', ['producto_id' => $producto->id]));

        $respuesta->assertOk();
        $items = $respuesta->viewData('items');
        $this->assertSame(40, $items->last()['stock_nuevo']);
        $this->assertSame(2, $items->count());
    }

    public function test_kardex_valorizado_usa_el_costo_actual_del_producto(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P006', 'nombre' => 'Perno', 'stock' => 20, 'precio_compra' => 3]);

        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 20, 'stock_anterior' => 0, 'stock_nuevo' => 20]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.kardex-valorizado', ['producto_id' => $producto->id]));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('items')->first();
        $this->assertSame(60.0, $fila['valor_movimiento']); // 20 * 3
        $this->assertSame(60.0, $fila['saldo_valorizado']);  // stock_nuevo(20) * 3
    }

    public function test_reporte_inventario_calcula_el_valor_a_costo_de_compra(): void
    {
        Producto::create(['codigo' => 'P007', 'nombre' => 'Placa', 'stock' => 15, 'precio_compra' => 12.5]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.reporte'));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('items')->firstWhere('codigo', 'P007');
        $this->assertSame(187.5, $fila['valor']); // 15 * 12.5
    }

    public function test_exportaciones_de_inventario_responden_con_el_content_type_correcto(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P008', 'nombre' => 'Producto Excel', 'stock' => 5, 'precio_compra' => 1]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 5, 'stock_anterior' => 0, 'stock_nuevo' => 5]);

        $admin = $this->actingAs($this->admin(), 'web');

        $excel = $admin->get(route('admin.inventario.reporte.excel'));
        $excel->assertOk();
        $excel->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $pdf = $admin->get(route('admin.inventario.kardex.pdf', ['producto_id' => $producto->id]));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }
}
