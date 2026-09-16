<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel del rol "ventas" (`ventas.index`, su página de inicio) mostraba
 * "Vendido hoy" filtrado a solo sus propias ventas por POS del día — una
 * venta de otro canal, de otro usuario, o de otro día, nunca aparecía ahí.
 * Ahora es un total real del negocio (todo canal, todo usuario), con un
 * segundo total por rango de fechas elegible.
 */
class VentasPanelTotalesTest extends TestCase
{
    use RefreshDatabase;

    private function ventas(): Usuario
    {
        return Usuario::create(['username' => 'ventas_'.uniqid(), 'password' => 'x', 'rol' => 'ventas']);
    }

    public function test_vendido_hoy_incluye_ventas_de_otro_usuario_y_de_backoffice(): void
    {
        $ella = $this->ventas();
        $otro = Usuario::create(['username' => 'otro_'.uniqid(), 'password' => 'x', 'rol' => 'ventas']);

        // Venta suya, por POS.
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 50, 'usuario_id' => $ella->id, 'canal' => 'pos',
        ]);
        // Venta de OTRO usuario, por POS.
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000002',
            'estado' => 'activa', 'total' => 30, 'usuario_id' => $otro->id, 'canal' => 'pos',
        ]);
        // Venta hecha por "Nueva Venta" (backoffice), sin usuario asignado.
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 20, 'canal' => 'backoffice',
        ]);

        $respuesta = $this->actingAs($ella, 'web')->get(route('ventas.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Venta total por rango');
        $respuesta->assertSee('id="ventasDesde"', false);
        $this->assertSame(3, $respuesta->viewData('ventasHoy'));
        $this->assertSame(100.0, $respuesta->viewData('montoHoy'));
    }

    public function test_venta_de_ayer_aparece_en_el_total_por_rango(): void
    {
        $ella = $this->ventas();

        Venta::create([
            'fecha' => now()->subDay()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 75, 'canal' => 'backoffice',
        ]);

        // Sin filtro explícito, el default es "hoy" para ambos — la venta de
        // ayer no debería colarse.
        $sinFiltro = $this->actingAs($ella, 'web')->get(route('ventas.index'));
        $this->assertSame(0, $sinFiltro->viewData('nVentasRango'));

        // Pidiendo el rango que incluye ayer, sí debe aparecer.
        $conRango = $this->actingAs($ella, 'web')->get(route('ventas.index', [
            'desde' => now()->subDays(2)->toDateString(),
            'hasta' => now()->toDateString(),
        ]));

        $this->assertSame(1, $conRango->viewData('nVentasRango'));
        $this->assertSame(75.0, $conRango->viewData('montoRango'));
    }

    public function test_cotizacion_y_anulada_no_suman(): void
    {
        $ella = $this->ventas();

        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 500,
        ]);
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'cancelada', 'total' => 200,
        ]);

        $respuesta = $this->actingAs($ella, 'web')->get(route('ventas.index'));

        $this->assertSame(0, $respuesta->viewData('ventasHoy'));
        $this->assertSame(0.0, $respuesta->viewData('montoHoy'));
    }

    public function test_nota_de_credito_resta_del_total(): void
    {
        $ella = $this->ventas();

        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 100,
        ]);
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '07', 'n_seri' => 'FC01', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 40,
        ]);

        $respuesta = $this->actingAs($ella, 'web')->get(route('ventas.index'));

        $this->assertSame(60.0, $respuesta->viewData('montoHoy'));
    }

    public function test_desglose_por_dia_trae_una_fila_por_dia_del_rango_mas_reciente_primero(): void
    {
        $ella = $this->ventas();

        Venta::create([
            'fecha' => now()->subDays(2)->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 100,
        ]);
        Venta::create([
            'fecha' => now()->subDay()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000002',
            'estado' => 'activa', 'total' => 30,
        ]);
        // Dos ventas el mismo día: deben acumularse en una sola fila.
        Venta::create([
            'fecha' => now()->subDay()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000003',
            'estado' => 'activa', 'total' => 20,
        ]);
        // Nota de crédito el mismo día: resta del total de esa fila.
        Venta::create([
            'fecha' => now()->subDay()->toDateString(), 'tipcomp' => '07', 'n_seri' => 'FC01', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 10,
        ]);

        $respuesta = $this->actingAs($ella, 'web')->get(route('ventas.index', [
            'desde' => now()->subDays(2)->toDateString(),
            'hasta' => now()->toDateString(),
        ]));

        $desglose = $respuesta->viewData('desglosePorDia');

        $this->assertCount(2, $desglose);
        $this->assertSame(now()->subDay()->toDateString(), $desglose[0]['fecha']);
        // 3 filas ese día (2 boletas + la nota de crédito) — "n" cuenta
        // filas igual que `nVentasRango`, la nota de crédito solo afecta
        // el signo del monto, no si se cuenta o no.
        $this->assertSame(3, $desglose[0]['n']);
        $this->assertSame(40.0, $desglose[0]['monto']);
        $this->assertSame(now()->subDays(2)->toDateString(), $desglose[1]['fecha']);
        $this->assertSame(1, $desglose[1]['n']);
        $this->assertSame(100.0, $desglose[1]['monto']);
    }

    public function test_desglose_por_medio_de_pago_engloba_tarjeta_transferencia_y_deposito_como_transferencia(): void
    {
        $ella = $this->ventas();
        $hoy = now()->toDateString();

        Venta::create(['fecha' => $hoy, 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'total' => 100, 'metodo_pago' => 'Efectivo']);
        Venta::create(['fecha' => $hoy, 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000002', 'estado' => 'activa', 'total' => 50, 'metodo_pago' => 'Yape']);
        Venta::create(['fecha' => $hoy, 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000003', 'estado' => 'activa', 'total' => 30, 'metodo_pago' => 'Plin']);
        Venta::create(['fecha' => $hoy, 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000004', 'estado' => 'activa', 'total' => 20, 'metodo_pago' => 'Tarjeta']);
        Venta::create(['fecha' => $hoy, 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000005', 'estado' => 'activa', 'total' => 15, 'metodo_pago' => 'Transferencia bancaria']);
        Venta::create(['fecha' => $hoy, 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000006', 'estado' => 'activa', 'total' => 10, 'metodo_pago' => 'Depósito bancario']);

        $respuesta = $this->actingAs($ella, 'web')->get(route('ventas.index'));

        $porMedio = $respuesta->viewData('ventasPorMedioPago')->keyBy('etiqueta');

        $this->assertSame(100.0, $porMedio['Efectivo']['monto']);
        $this->assertSame(50.0, $porMedio['Yape']['monto']);
        $this->assertSame(30.0, $porMedio['Plin']['monto']);
        // Tarjeta + Transferencia bancaria + Depósito bancario = 20+15+10 = 45.
        $this->assertSame(45.0, $porMedio['Transferencia']['monto']);
        $this->assertSame(3, $porMedio['Transferencia']['n']);
        $this->assertArrayNotHasKey('Sin especificar', $porMedio);
    }

    public function test_desglose_por_medio_de_pago_muestra_sin_especificar_solo_si_hay_algo(): void
    {
        $ella = $this->ventas();

        Venta::create(['fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'total' => 100, 'metodo_pago' => null]);

        $respuesta = $this->actingAs($ella, 'web')->get(route('ventas.index'));
        $porMedio = $respuesta->viewData('ventasPorMedioPago')->keyBy('etiqueta');

        $this->assertSame(100.0, $porMedio['Sin especificar']['monto']);
        $this->assertSame(0.0, $porMedio['Efectivo']['monto']);
    }
}
