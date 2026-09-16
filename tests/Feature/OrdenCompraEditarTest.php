<?php

namespace Tests\Feature;

use App\Models\OrdenCompra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editar una orden ya creada (`_form.blade.php`, alcanzable desde "Editar
 * orden" en el detalle) permite tocar también sus líneas de producto, no
 * solo los datos de cabecera — y el PDF (`pdf.blade.php`) siempre lee
 * `$orden->productos` en vivo desde la base, así que un cambio guardado se
 * ve reflejado sin ningún paso extra.
 */
class OrdenCompraEditarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_editar_una_orden_permite_cambiar_los_productos_y_el_pdf_lo_refleja(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->post(route('admin.ordenes-compra.store'), [
            'estado' => 'Pendiente', 'gasto_unit' => '0', 'condicion_pago' => 'contado', 'tc' => '1',
            'proveedor' => 'Distribuidora Drywall SAC', 'ruc' => '20123456789',
            'numero_orden' => 'OC-EDIT-001', 'fecha' => now()->toDateString(),
            'precio_venta' => '0', 'total_usd' => '19.00', 'total_soles' => '19.00',
            'productos' => json_encode([
                ['codigo' => '00001', 'descripcion' => 'PARANTE GALV. 89 X 38 X 0.45 X 3M', 'unidad' => 'Und.', 'precio_unit_usd' => 9.5, 'cantidad' => 2],
            ]),
        ])->assertRedirect();

        $orden = OrdenCompra::where('numero_orden', 'OC-EDIT-001')->firstOrFail();

        $paginaEdicion = $this->actingAs($admin, 'web')->get(route('admin.ordenes-compra.edit', $orden));
        $paginaEdicion->assertOk();
        $paginaEdicion->assertSee('PARANTE GALV. 89 X 38 X 0.45 X 3M', false);

        $respuestaUpdate = $this->actingAs($admin, 'web')->put(route('admin.ordenes-compra.update', $orden), [
            'estado' => 'Pendiente', 'proveedor' => 'Distribuidora Drywall SAC', 'ruc' => '20123456789',
            'numero_orden' => 'OC-EDIT-001', 'fecha' => now()->toDateString(),
            'productos' => [
                ['codigo' => '00001', 'descripcion' => 'PARANTE GALV. 89 X 38 X 0.45 X 3M', 'cantidad' => 5, 'precio' => 11.25],
                ['codigo' => '00002', 'descripcion' => 'PLACA NUEVA AGREGADA EN LA EDICION', 'cantidad' => 3, 'precio' => 20],
            ],
            'total_usd' => '116.25', 'total_soles' => '116.25',
        ]);
        $respuestaUpdate->assertRedirect(route('admin.ordenes-compra.index'));

        $orden->refresh();
        $this->assertCount(2, $orden->productos);
        $this->assertSame(5, (int) $orden->productos[0]['cantidad']);
        $this->assertSame('PLACA NUEVA AGREGADA EN LA EDICION', $orden->productos[1]['descripcion']);

        $html = view('admin.ordenes-compra.pdf', ['ordenes' => collect([$orden])])->render();
        $this->assertStringContainsString('PLACA NUEVA AGREGADA EN LA EDICION', $html);
        $this->assertStringContainsString('11.25', $html);
    }
}
