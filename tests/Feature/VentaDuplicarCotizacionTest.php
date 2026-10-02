<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Botón "Duplicar" en el listado de Cotizaciones (pedido del negocio): crea
 * una Cotización nueva e independiente con los mismos datos, para no tener
 * que volver a tipear todo. Como Cotización ya está excluida de todos los
 * totales/reportes/stock en el resto del sistema, duplicarla no debería
 * desalinear nada de eso — estas pruebas lo confirman directamente.
 */
class VentaDuplicarCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function cotizacionConItems(): Venta
    {
        $producto = Producto::create(['codigo' => 'P100', 'nombre' => 'Plancha Drywall', 'precio_compra' => 20, 'precio_venta' => 30]);

        $cot = Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'estado' => 'activa', 'razonsocial' => 'Cliente de Prueba SAC', 'n_ruc' => '20123456789',
            'cliente_direccion' => 'Av. Siempre Viva 123', 'baseimp' => 100, 'igv' => 18, 'total' => 118,
        ]);
        VentaDetalle::create([
            'venta_id' => $cot->id, 'producto_id' => $producto->id, 'prod_codigo' => 'P100',
            'prod_nombre' => 'Plancha Drywall', 'cantidad' => 2, 'precio_unitario' => 30, 'subtotal' => 60,
        ]);

        return $cot;
    }

    public function test_duplicar_crea_una_cotizacion_nueva_e_independiente(): void
    {
        $cot = $this->cotizacionConItems();

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.duplicar', $cot));

        $respuesta->assertRedirect();
        $this->assertSame(2, Venta::where('tipcomp', 'COT')->count());

        $nueva = Venta::where('tipcomp', 'COT')->where('id', '!=', $cot->id)->firstOrFail();
        $this->assertNotSame($cot->n_comp, $nueva->n_comp, 'Debe sacar su propio correlativo, no repetir el de la original.');
        $this->assertSame('Cliente de Prueba SAC', $nueva->razonsocial);
        $this->assertSame('20123456789', $nueva->n_ruc);
        $this->assertSame('Av. Siempre Viva 123', $nueva->cliente_direccion);
        $this->assertEquals(118.0, (float) $nueva->total);
        $this->assertNull($nueva->origen_cotizacion_id, 'No es "generada desde" la original — es una copia independiente.');

        $detalle = VentaDetalle::where('venta_id', $nueva->id)->firstOrFail();
        $this->assertSame('P100', $detalle->prod_codigo);
        $this->assertEquals(2, $detalle->cantidad);

        // La original no se tocó.
        $this->assertSame(1, VentaDetalle::where('venta_id', $cot->id)->count());
    }

    public function test_duplicar_no_afecta_el_stock(): void
    {
        $almacen = Almacen::create(['nombre' => 'Principal', 'activo' => true]);
        $producto = Producto::create(['codigo' => 'P101', 'nombre' => 'Tornillo', 'precio_compra' => 1, 'precio_venta' => 2]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 50]);

        $cot = Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'estado' => 'activa', 'razonsocial' => 'Cliente de Prueba', 'total' => 20,
        ]);
        VentaDetalle::create([
            'venta_id' => $cot->id, 'producto_id' => $producto->id, 'prod_codigo' => 'P101',
            'prod_nombre' => 'Tornillo', 'cantidad' => 10, 'precio_unitario' => 2, 'subtotal' => 20,
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.duplicar', $cot));

        $this->assertSame(50.0, (float) StockAlmacen::where('producto_id', $producto->id)->first()->stock);
    }

    public function test_duplicar_no_afecta_el_total_del_panel_de_ventas(): void
    {
        $cot = $this->cotizacionConItems();

        $usuarioVentas = Usuario::create(['username' => 'ventas_'.uniqid(), 'password' => 'x', 'rol' => 'ventas']);
        $antes = $this->actingAs($usuarioVentas, 'web')->get(route('ventas.index', [
            'desde' => '2026-09-01', 'hasta' => '2026-09-01',
        ]));
        $this->assertSame(0.0, $antes->viewData('montoRango'));

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.duplicar', $cot));

        $despues = $this->actingAs($usuarioVentas, 'web')->get(route('ventas.index', [
            'desde' => '2026-09-01', 'hasta' => '2026-09-01',
        ]));
        $this->assertSame(0.0, $despues->viewData('montoRango'), 'Las Cotizaciones (la original y la copia) siguen sin sumar.');
    }

    public function test_no_se_puede_duplicar_una_venta_que_no_es_cotizacion(): void
    {
        $boleta = Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 100,
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.duplicar', $boleta))
            ->assertNotFound();
    }
}
