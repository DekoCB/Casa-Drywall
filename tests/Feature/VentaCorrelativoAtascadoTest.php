<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El negocio reportó "no me deja registrar factura" — causa real
 * (confirmada en producción, venta #211): una Factura que nunca se
 * registró ante SUNAT (ej. rechazada por bancarización) deja el contador
 * real de SUNAT congelado en ese número. Como la sugerencia de "siguiente
 * N° Comprobante" se arma a partir de ese contador, TODAS las Facturas
 * nuevas seguían sugiriendo el mismo número ya usado localmente — cada una
 * chocaba contra el duplicado y quedaba bloqueada, hasta que alguien
 * arreglara esa primera a mano.
 */
class VentaCorrelativoAtascadoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    /** API-GO dice que el último correlativo real que asignó fue 1868 (001869 sigue sin registrarse). */
    private function fakeCorrelativoCongeladoEn1868(): void
    {
        Http::fake(['*/branches/*/correlatives*' => Http::response(['data' => ['correlatives' => [
            ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo_actual' => 1868],
        ]]], 200)]);
    }

    public function test_la_sugerencia_salta_un_numero_que_ya_existe_local_sin_registrar(): void
    {
        $this->fakeCorrelativoCongeladoEn1868();

        // La Factura #211 real: existe local, nunca se registró ante SUNAT.
        Venta::create([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '001869', 'fecha' => '2026-10-06',
            'razonsocial' => 'Contratistas Generales Ica S.A.C.', 'estado' => 'activa',
            'api_go_document_id' => null, 'estado_factura' => 'pendiente',
        ]);

        $admin = $this->admin();
        $respuesta = $this->actingAs($admin, 'web')->get(route('admin.ventas.factura.create'));

        $respuesta->assertOk();
        // La opción de Factura ya no debe ofrecer 001869 (atascado) — debe
        // saltar al siguiente número de verdad libre.
        $respuesta->assertDontSee('data-comp="001869"', false);
        $respuesta->assertSee('data-comp="001870"', false);
    }

    public function test_la_sugerencia_salta_varios_numeros_atascados_seguidos(): void
    {
        $this->fakeCorrelativoCongeladoEn1868();

        foreach (['001869', '001870', '001871'] as $n) {
            Venta::create([
                'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => $n, 'fecha' => '2026-10-06',
                'razonsocial' => 'Cliente', 'estado' => 'activa',
                'api_go_document_id' => null, 'estado_factura' => 'pendiente',
            ]);
        }

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->get(route('admin.ventas.factura.create'))
            ->assertOk()
            ->assertSee('data-comp="001872"', false);
    }

    public function test_sin_numeros_atascados_la_sugerencia_no_cambia(): void
    {
        $this->fakeCorrelativoCongeladoEn1868();

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->get(route('admin.ventas.factura.create'))
            ->assertOk()
            ->assertSee('data-comp="001869"', false);
    }

    public function test_el_error_de_duplicado_sugiere_reintentar_si_nunca_se_registro(): void
    {
        Venta::create([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '001869', 'fecha' => '2026-10-06',
            'razonsocial' => 'Cliente', 'estado' => 'activa',
            'api_go_document_id' => null, 'estado_factura' => 'pendiente',
        ]);

        $admin = $this->admin();
        $respuesta = $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-10-07', 'fecha_vencimiento' => '2026-10-07', 'tipcomp' => '01',
            'n_seri' => 'F001', 'n_comp' => '001869', 'razonsocial' => 'Otro Cliente',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 100]],
        ]);

        $respuesta->assertSessionHas('error', function ($mensaje) {
            return str_contains($mensaje, 'Reintentar registro SUNAT');
        });
        $this->assertSame(1, Venta::where('n_comp', '001869')->count());
    }

    public function test_el_error_de_duplicado_no_sugiere_reintentar_si_ya_esta_registrado(): void
    {
        Venta::create([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '001868', 'fecha' => '2026-10-06',
            'razonsocial' => 'Cliente', 'estado' => 'activa',
            'api_go_document_id' => 17, 'estado_factura' => 'registrado',
        ]);

        $admin = $this->admin();
        $respuesta = $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-10-07', 'fecha_vencimiento' => '2026-10-07', 'tipcomp' => '01',
            'n_seri' => 'F001', 'n_comp' => '001868', 'razonsocial' => 'Otro Cliente',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 100]],
        ]);

        $respuesta->assertSessionHas('error', function ($mensaje) {
            return ! str_contains($mensaje, 'Reintentar registro SUNAT');
        });
    }
}
