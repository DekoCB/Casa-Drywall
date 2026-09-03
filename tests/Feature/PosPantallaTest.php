<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use App\Services\Pos\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test de las pantallas (no solo del servicio): confirma que las
 * vistas Blade del POS y de Cajas renderizan sin errores, con y sin
 * sesión de caja abierta.
 */
class PosPantallaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pantalla_pos_renderiza_sin_caja_abierta(): void
    {
        $usuario = Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        Caja::create(['nombre' => 'Caja 01', 'activo' => true]);
        Almacen::create(['nombre' => 'Principal', 'activo' => true]);

        $this->actingAs($usuario, 'web')
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('No tienes una caja abierta');
    }

    public function test_pantalla_pos_renderiza_con_caja_abierta_y_productos(): void
    {
        $usuario = Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $almacen = Almacen::create(['nombre' => 'Principal', 'activo' => true]);
        $producto = Producto::create(['nombre' => 'Producto Smoke', 'codigo' => 'SMK-1', 'precio_venta' => 10]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 5]);

        $caja = Caja::create(['nombre' => 'Caja 01', 'activo' => true]);
        app(CajaService::class)->abrir($usuario, $caja->id, 100);

        $this->actingAs($usuario, 'web')
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Carrito')
            ->assertSee('Caja 01');
    }

    public function test_pantalla_pos_accesible_para_secretaria(): void
    {
        $usuario = Usuario::create(['username' => 'sec_'.uniqid(), 'password' => 'x', 'rol' => 'secretaria']);

        $this->actingAs($usuario, 'web')
            ->get(route('admin.pos.index'))
            ->assertOk();
    }

    public function test_pantalla_pos_no_accesible_para_contador(): void
    {
        $usuario = Usuario::create(['username' => 'cont_'.uniqid(), 'password' => 'x', 'rol' => 'contador']);

        $this->actingAs($usuario, 'web')
            ->get(route('admin.pos.index'))
            ->assertRedirect();
    }

    public function test_pantalla_caja_backoffice_renderiza(): void
    {
        $usuario = Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        Caja::create(['nombre' => 'Caja 01', 'activo' => true]);

        $this->actingAs($usuario, 'web')
            ->get(route('admin.caja.index'))
            ->assertOk()
            ->assertSee('Caja 01');
    }

    public function test_categorias_y_marcas_ahora_enlazadas_desde_el_menu_renderizan(): void
    {
        $usuario = Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);

        $this->actingAs($usuario, 'web')->get(route('admin.categorias.index'))->assertOk();
        $this->actingAs($usuario, 'web')->get(route('admin.marcas.index'))->assertOk();
    }

    public function test_modal_de_cuenta_incluye_tarjetas_de_configuracion_solo_para_admin(): void
    {
        $admin = Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $secretaria = Usuario::create(['username' => 'sec_'.uniqid(), 'password' => 'x', 'rol' => 'secretaria']);

        $this->actingAs($admin, 'web')
            ->get(route('admin.caja.index'))
            ->assertOk()
            ->assertSee('Catálogo')
            ->assertSee('Galonaje')
            ->assertSee('href="'.route('admin.categorias.index').'"', false);

        $this->actingAs($secretaria, 'web')
            ->get(route('secretaria.index'))
            ->assertOk()
            ->assertDontSee('Galonaje');
    }

    public function test_buscar_productos_ajax_devuelve_json(): void
    {
        $usuario = Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $almacen = Almacen::create(['nombre' => 'Principal', 'activo' => true]);
        $producto = Producto::create(['nombre' => 'Placa Drywall', 'codigo' => 'DRY-1', 'precio_venta' => 15]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 7]);

        $this->actingAs($usuario, 'web')
            ->get(route('admin.pos.productos.buscar', ['q' => 'Drywall', 'almacen_id' => $almacen->id]))
            ->assertOk()
            ->assertJsonFragment(['codigo' => 'DRY-1', 'stock' => 7]);
    }
}
