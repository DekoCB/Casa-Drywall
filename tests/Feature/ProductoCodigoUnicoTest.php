<?php

namespace Tests\Feature;

use App\Models\Producto;
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
}
