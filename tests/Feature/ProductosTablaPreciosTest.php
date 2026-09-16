<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El listado de Productos ahora muestra precio de compra y de venta, uno
 * junto al otro — Viscosidad (un dato de Kendall que ya no aplica al
 * catálogo real de drywall) se quitó para hacerle espacio, y Precio Alquiler
 * (tampoco usado) se quitó del modal de alta/edición.
 *
 * Los precios de compra y venta se muestran con 4 decimales (antes 2): el
 * negocio necesita esa precisión para costos por unidad que salen de
 * prorratear una compra al por mayor (ej. el costo de un tornillo dentro de
 * una caja de 1000).
 */
class ProductosTablaPreciosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_listado_muestra_precio_de_compra_junto_al_de_venta_sin_viscosidad(): void
    {
        Producto::create([
            'codigo' => 'DRY-500', 'nombre' => 'Placa de prueba', 'estado' => 'activo',
            'precio_compra' => 12.3456, 'precio_venta' => 20, 'stock' => 0, 'stock_minimo' => 0,
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.productos.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('<th>Precio compra</th>', false);
        $respuesta->assertSee('<th>Precio venta</th>', false);
        $respuesta->assertDontSee('<th>Viscosidad</th>', false);
        $respuesta->assertSee('S/ 12.3456', false);
    }

    public function test_el_modal_ya_no_pide_viscosidad_ni_precio_de_alquiler(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.productos.index'));

        $respuesta->assertOk();
        $respuesta->assertDontSee('id="viscosidad"', false);
        $respuesta->assertDontSee('id="precio_alquiler"', false);
        $respuesta->assertDontSee('Precio Alquiler');
    }
}
