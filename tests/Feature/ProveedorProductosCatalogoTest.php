<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo de productos ya no se carga por Excel proveedor por
 * proveedor (ver `ProveedorProductosImportTest`, que sigue probando ese
 * endpoint porque el controlador no se borró) — es una sola lista, la
 * misma para cualquier proveedor, y se edita en el catálogo general de
 * Productos. El listado de Proveedores enlaza para allá en vez de ofrecer
 * la carga por Excel fila por fila.
 */
class ProveedorProductosCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_listado_de_proveedores_enlaza_al_catalogo_de_productos_en_vez_de_excel_por_fila(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.proveedores.index'));

        $respuesta->assertOk();
        $respuesta->assertSee(route('admin.productos.index'), false);
        $respuesta->assertDontSee('btn-cargar-productos', false);
        $respuesta->assertDontSee('modalProductosProveedor', false);
    }
}
