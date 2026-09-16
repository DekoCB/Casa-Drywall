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

    public function test_traslado_nuevo_queda_en_curso_y_admite_editar_la_cantidad(): void
    {
        [$origen, $destino] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P100', 'nombre' => 'Placa', 'stock' => 50]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $origen->id, 'stock' => 50]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.inventario.traslados.store'), [
            'producto_id' => $producto->id,
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'cantidad' => 20,
        ]);

        $movs = MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'traslado')->get();
        $this->assertTrue($movs->every(fn (MovimientoAlmacen $m) => $m->estado === 'en_curso'));

        // Se corrige: en realidad eran 15, no 20 — debe corregir ambos almacenes a la vez.
        $movOrigen = $movs->firstWhere('almacen_id', $origen->id);
        $this->actingAs($this->admin(), 'web')
            ->patch(route('admin.inventario.movimientos.cantidad', $movOrigen), ['cantidad' => 15])
            ->assertRedirect();

        $this->assertSame(35, StockAlmacen::where('almacen_id', $origen->id)->value('stock')); // 50-15
        $this->assertSame(15, StockAlmacen::where('almacen_id', $destino->id)->value('stock'));
        $this->assertTrue(MovimientoAlmacen::where('producto_id', $producto->id)->get()->every(fn (MovimientoAlmacen $m) => $m->cantidad === 15));
    }

    public function test_marcar_entregado_bloquea_la_edicion_de_cantidad(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P101', 'nombre' => 'Cinta', 'stock' => 20]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 20]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.inventario.devoluciones.store'), [
            'producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'cantidad' => 5,
        ]);
        $mov = MovimientoAlmacen::where('producto_id', $producto->id)->firstOrFail();

        $this->actingAs($this->admin(), 'web')
            ->patch(route('admin.inventario.movimientos.entregado', $mov))
            ->assertRedirect();
        $this->assertSame('entregado', $mov->fresh()->estado);

        $this->actingAs($this->admin(), 'web')
            ->patch(route('admin.inventario.movimientos.cantidad', $mov), ['cantidad' => 10])
            ->assertSessionHas('error');

        $this->assertSame(15, StockAlmacen::where('almacen_id', $almacen->id)->value('stock')); // sin cambios
        $this->assertSame(5, $mov->fresh()->cantidad);
    }

    public function test_entrada_manual_queda_entregada_de_una_y_no_se_puede_editar(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P103', 'nombre' => 'Cinta métrica', 'stock' => 0]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 0]);

        // Entrada/salida/ajuste terminan en el mismo clic, en un solo
        // almacén: no hay tránsito físico que alguien deba confirmar después
        // (a diferencia de traslado/devolución, que sí empiezan "en curso").
        $this->actingAs($this->admin(), 'web')->post(route('admin.productos.stock', $producto), [
            'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 30,
        ]);

        $mov = MovimientoAlmacen::where('producto_id', $producto->id)->firstOrFail();
        $this->assertSame('entregado', $mov->estado);

        $this->actingAs($this->admin(), 'web')
            ->patch(route('admin.inventario.movimientos.cantidad', $mov), ['cantidad' => 50])
            ->assertSessionHas('error');
    }

    public function test_editar_cantidad_de_un_ajuste_es_rechazado(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P102', 'nombre' => 'Tornillo', 'stock' => 100]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 100]);
        $mov = MovimientoAlmacen::create([
            'producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'ajuste',
            'cantidad' => 90, 'stock_anterior' => 100, 'stock_nuevo' => 90, 'estado' => 'en_curso',
        ]);

        $this->actingAs($this->admin(), 'web')
            ->patch(route('admin.inventario.movimientos.cantidad', $mov), ['cantidad' => 80])
            ->assertSessionHas('error');

        $this->assertSame(90, $mov->fresh()->cantidad);
    }

    public function test_listado_de_movimientos_filtra_por_tipo(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P004', 'nombre' => 'Tornillo', 'stock' => 100]);

        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 100, 'stock_anterior' => 0, 'stock_nuevo' => 100]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'ajuste', 'cantidad' => 90, 'stock_anterior' => 100, 'stock_nuevo' => 90]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.movimientos.historial', ['tipo' => 'ajuste']));

        $respuesta->assertOk();
        $tipos = $respuesta->viewData('movimientos')->pluck('tipo');
        $this->assertSame(['ajuste'], $tipos->unique()->values()->all());
    }

    public function test_movimientos_lista_una_fila_por_producto_y_almacen_incluidos_los_de_stock_cero(): void
    {
        [$almacen1, $almacen2] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P010', 'nombre' => 'Clavo', 'stock' => 0]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen1->id, 'stock' => 0]);
        // Sin fila en StockAlmacen para $almacen2: debe igual aparecer, coalescida a 0.

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.movimientos'));

        $respuesta->assertOk();
        $filas = $respuesta->viewData('items')->where('producto_id', $producto->id);
        $this->assertCount(2, $filas);
        $this->assertTrue($filas->every(fn ($f) => $f['stock'] === 0));
    }

    /**
     * Los formularios de Trasladar/Remover/Ajuste/Nuevo movimiento pedían la
     * cantidad a ciegas, sin decir cuánto había en ese momento en el
     * almacén elegido. Ahora cada modal trae, por producto, su stock por
     * almacén embebido (`p.stocks`) y un aviso "Stock actual" que se
     * actualiza en vivo con JS al elegir producto/almacén — acá solo se
     * confirma que el HTML y los datos necesarios están en la página.
     */
    public function test_movimientos_trae_el_stock_por_almacen_para_el_aviso_de_cantidad(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P015', 'nombre' => 'Tornillo Drywall', 'stock' => 40]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 40]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.movimientos'));

        $respuesta->assertOk();
        $respuesta->assertSee('data-stock-actual', false);
        $respuesta->assertSee('data-campo-stock-almacen', false);
        $respuesta->assertSee('"stocks":{"'.$almacen->id.'":40}', false);
    }

    public function test_historial_trae_el_stock_por_almacen_para_el_aviso_de_cantidad(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P016', 'nombre' => 'Cinta Papel', 'stock' => 12]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 12]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.movimientos.historial'));

        $respuesta->assertOk();
        $respuesta->assertSee('data-stock-actual', false);
        $respuesta->assertSee('data-campo-stock-almacen', false);
        $respuesta->assertSee('"stocks":{"'.$almacen->id.'":12}', false);
    }

    public function test_movimientos_filtra_por_almacen(): void
    {
        [$almacen1, $almacen2] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P011', 'nombre' => 'Tuerca', 'stock' => 5]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen1->id, 'stock' => 5]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.movimientos', ['almacen_id' => $almacen1->id]));

        $respuesta->assertOk();
        $filas = $respuesta->viewData('items')->where('producto_id', $producto->id);
        $this->assertCount(1, $filas);
        $this->assertSame($almacen1->id, $filas->first()['almacen_id']);
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

    public function test_kardex_valorizado_lista_una_fila_por_producto_con_el_costo_actual(): void
    {
        Producto::create(['codigo' => 'P006', 'nombre' => 'Perno', 'stock' => 20, 'precio_compra' => 3]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.kardex-valorizado'));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('items')->firstWhere('codigo', 'P006');
        $this->assertSame(3.0, $fila['costo_ponderado']);
        $this->assertSame(60.0, $fila['costo_producto']); // stock(20) * costo(3)
    }

    public function test_reporte_inventario_calcula_el_valor_a_costo_de_compra(): void
    {
        Producto::create(['codigo' => 'P007', 'nombre' => 'Placa', 'stock' => 15, 'precio_compra' => 12.5]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.reporte'));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('items')->firstWhere('codigo', 'P007');
        $this->assertSame(187.5, $fila['valor']); // 15 * 12.5
    }

    /**
     * El cliente reportó que el stock de "Inventario" (por almacén) no
     * coincidía con "Reporte de Inventario" (antes siempre la suma de TODOS
     * los almacenes, sin poder elegir uno). Con el mismo almacén elegido en
     * las dos pantallas, ahora sí dan el mismo número — porque las dos leen
     * `stock_almacen` directamente en vez de que una use el acumulado
     * `Producto.stock` y la otra el detalle por almacén.
     */
    public function test_reporte_inventario_con_almacen_elegido_coincide_con_inventario_por_almacen(): void
    {
        [$almacen1, $almacen2] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P012', 'nombre' => 'Plancha', 'stock' => 30, 'precio_compra' => 4]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen1->id, 'stock' => 10]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen2->id, 'stock' => 20]);

        $sinFiltro = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.reporte'));
        $this->assertSame(30, $sinFiltro->viewData('items')->firstWhere('codigo', 'P012')['stock']);

        $conFiltro = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.reporte', ['almacen_id' => $almacen1->id]));
        $filaReporte = $conFiltro->viewData('items')->firstWhere('codigo', 'P012');
        $this->assertSame(10, $filaReporte['stock']);

        $inventario = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.inventario.movimientos', ['almacen_id' => $almacen1->id]));
        $filaInventario = $inventario->viewData('items')->firstWhere('producto_id', $producto->id);

        $this->assertSame($filaInventario['stock'], $filaReporte['stock']);
    }

    public function test_historial_de_movimientos_muestra_el_stock_actual_del_producto(): void
    {
        [$almacen] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P013', 'nombre' => 'Angular', 'stock' => 45]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 45]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'entrada', 'cantidad' => 50, 'stock_anterior' => 0, 'stock_nuevo' => 50]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'tipo' => 'salida', 'cantidad' => 5, 'stock_anterior' => 50, 'stock_nuevo' => 45]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.movimientos.historial'));

        $respuesta->assertOk();
        $respuesta->assertSee('Stock actual');
        // Las dos filas históricas muestran el MISMO stock actual (45), no
        // solo su propio "stock_nuevo" de ese momento.
        $respuesta->assertSeeInOrder(['45', '45'], false);
    }

    /**
     * El cliente reportó que el stock de "Movimientos" (por almacén) y el
     * de "reportes de Movimiento" (este historial) no coincidían — porque
     * la columna "Stock actual" mostraba el ACUMULADO de todos los
     * almacenes (`Producto.stock`), mientras Movimientos siempre separa
     * por almacén. Con dos almacenes distintos para el mismo producto,
     * cada fila del historial ahora muestra el stock de SU PROPIO almacén,
     * igual que Movimientos.
     */
    public function test_historial_muestra_el_stock_de_cada_almacen_no_el_acumulado(): void
    {
        [$almacen1, $almacen2] = $this->dosAlmacenes();
        $producto = Producto::create(['codigo' => 'P014', 'nombre' => 'Perfil U', 'stock' => 50]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen1->id, 'stock' => 30]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen2->id, 'stock' => 20]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen1->id, 'tipo' => 'entrada', 'cantidad' => 30, 'stock_anterior' => 0, 'stock_nuevo' => 30]);
        MovimientoAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen2->id, 'tipo' => 'entrada', 'cantidad' => 20, 'stock_anterior' => 0, 'stock_nuevo' => 20]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.movimientos.historial'));

        $respuesta->assertOk();
        $filas = $respuesta->viewData('movimientos')->getCollection();
        $filaAlmacen1 = $filas->firstWhere('almacen_id', $almacen1->id);
        $filaAlmacen2 = $filas->firstWhere('almacen_id', $almacen2->id);

        $this->assertSame(30, $filaAlmacen1->stock_actual_almacen);
        $this->assertSame(20, $filaAlmacen2->stock_actual_almacen);
        // Ninguna de las dos es el acumulado (50) — cada una es su propio almacén.
        $this->assertNotSame(50, $filaAlmacen1->stock_actual_almacen);
    }

    public function test_reporte_inventario_calcula_la_utilidad_unitaria(): void
    {
        Producto::create(['codigo' => 'P009', 'nombre' => 'Perfil', 'stock' => 10, 'precio_compra' => 8, 'precio_venta' => 12]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.inventario.reporte'));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('items')->firstWhere('codigo', 'P009');
        $this->assertSame(4.0, $fila['utilidad']); // 12 - 8
        $this->assertEqualsWithDelta(33.3, $fila['utilidad_pct'], 0.1); // 4/12
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

        $kardexValorizadoExcel = $admin->get(route('admin.inventario.kardex-valorizado.excel'));
        $kardexValorizadoExcel->assertOk();
        $kardexValorizadoExcel->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
