<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Usuario;
use App\Services\Pos\CajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_y_cerrar_una_sesion_de_caja(): void
    {
        $usuario = Usuario::create(['username' => 'cajero_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $caja = Caja::create(['nombre' => 'Caja 01', 'activo' => true]);
        $servicio = app(CajaService::class);

        $sesion = $servicio->abrir($usuario, $caja->id, 200);

        $this->assertSame('abierta', $sesion->estado);
        $this->assertEquals(200, $sesion->fresh()->monto_inicial);
        $this->assertNotNull($servicio->sesionAbiertaDe($usuario));

        $cerrada = $servicio->cerrar($sesion, 195);

        $this->assertSame('cerrada', $cerrada->estado);
        $this->assertNull($servicio->sesionAbiertaDe($usuario));
    }

    public function test_no_se_puede_abrir_dos_sesiones_para_el_mismo_usuario(): void
    {
        $usuario = Usuario::create(['username' => 'cajero_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $caja1 = Caja::create(['nombre' => 'Caja 01', 'activo' => true]);
        $caja2 = Caja::create(['nombre' => 'Caja 02', 'activo' => true]);
        $servicio = app(CajaService::class);

        $servicio->abrir($usuario, $caja1->id, 100);

        $this->expectException(ValidationException::class);
        $servicio->abrir($usuario, $caja2->id, 100);
    }

    public function test_calcular_esperado_solo_cuenta_movimientos_en_efectivo(): void
    {
        $usuario = Usuario::create(['username' => 'cajero_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $caja = Caja::create(['nombre' => 'Caja 01', 'activo' => true]);
        $sesion = app(CajaService::class)->abrir($usuario, $caja->id, 100);

        MovimientoCaja::create(['sesion_caja_id' => $sesion->id, 'tipo' => 'venta', 'metodo_pago' => 'Efectivo', 'monto' => 50]);
        MovimientoCaja::create(['sesion_caja_id' => $sesion->id, 'tipo' => 'venta', 'metodo_pago' => 'Yape', 'monto' => 300]);
        MovimientoCaja::create(['sesion_caja_id' => $sesion->id, 'tipo' => 'venta', 'metodo_pago' => 'Efectivo', 'monto' => -5]);

        // 100 inicial + 50 - 5 en efectivo = 145. Los 300 de Yape nunca
        // debieron sumar: ese dinero no pasa por el cajón físico.
        $this->assertEquals(145.0, $sesion->calcularEsperado());
    }
}
