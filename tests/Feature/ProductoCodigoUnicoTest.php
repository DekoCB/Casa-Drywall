<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `productos.codigo` ahora es único de verdad (migración
 * `unique_codigo_en_productos`) — varias rondas de carga de listas en
 * sesiones anteriores habían terminado creando una fila nueva en vez de
 * actualizar la existente para 10 códigos reales, y las ventas se fueron
 * enlazando a la fila equivocada (el costo mostrado en Utilidades no
 * correspondía al producto real de verdad). La migración de datos
 * `consolidar_productos_duplicados` corrigió lo ya existente en
 * producción; esta restricción evita que vuelva a pasar.
 */
class ProductoCodigoUnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_crear_dos_productos_con_el_mismo_codigo(): void
    {
        Producto::create(['codigo' => 'DUP-001', 'nombre' => 'Producto A']);

        $this->expectException(QueryException::class);

        Producto::create(['codigo' => 'DUP-001', 'nombre' => 'Producto B']);
    }

    /** Un código vacío/nulo (línea manual sin catálogo) sigue sin exigir unicidad. */
    public function test_varios_productos_sin_codigo_si_se_pueden_crear(): void
    {
        Producto::create(['codigo' => null, 'nombre' => 'Producto sin código 1']);
        Producto::create(['codigo' => null, 'nombre' => 'Producto sin código 2']);

        $this->assertSame(2, Producto::whereNull('codigo')->count());
    }

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosProducto(array $overrides = []): array
    {
        return array_merge([
            'codigo' => 'DUP-002', 'nombre' => 'Placa Drywall', 'precio_compra' => 10,
            'precio_venta' => 15, 'stock_minimo' => 1,
        ], $overrides);
    }

    /**
     * Antes, intentar guardar un código repetido desde "Agregar producto"
     * tiraba un 500 crudo (UniqueConstraintViolationException sin
     * capturar) en vez de un error claro — la restricción de la base ya
     * protegía los datos, pero no había validación de Laravel delante que
     * lo atajara con un mensaje entendible.
     */
    public function test_crear_con_codigo_repetido_da_un_error_claro_no_un_500(): void
    {
        Producto::create(['codigo' => 'DUP-002', 'nombre' => 'Superboard existente']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->post(route('admin.productos.store'), $this->datosProducto());

        $respuesta->assertSessionHasErrors('codigo');
        $this->assertSame(1, Producto::where('codigo', 'DUP-002')->count());
    }

    /** Editar un producto sin cambiar su propio código no debe chocar consigo mismo. */
    public function test_editar_sin_cambiar_el_codigo_no_falla(): void
    {
        $producto = Producto::create(['codigo' => 'DUP-003', 'nombre' => 'Placa Drywall']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->put(route('admin.productos.update', $producto), $this->datosProducto(['codigo' => 'DUP-003', 'nombre' => 'Placa Drywall editada']));

        $respuesta->assertSessionDoesntHaveErrors();
        $this->assertSame('Placa Drywall editada', $producto->fresh()->nombre);
    }

    /** Editar para usar el código de OTRO producto sí debe rechazarse. */
    public function test_editar_al_codigo_de_otro_producto_es_rechazado(): void
    {
        Producto::create(['codigo' => 'DUP-004', 'nombre' => 'Producto A']);
        $productoB = Producto::create(['codigo' => 'DUP-005', 'nombre' => 'Producto B']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->put(route('admin.productos.update', $productoB), $this->datosProducto(['codigo' => 'DUP-004']));

        $respuesta->assertSessionHasErrors('codigo');
        $this->assertSame('DUP-005', $productoB->fresh()->codigo);
    }
}
