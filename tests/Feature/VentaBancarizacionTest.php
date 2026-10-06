<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use App\Services\Sunat\ApiGoEmisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ley N° 28194 (Bancarización): una Factura mayor a S/ 2,000 necesita los
 * datos del medio de pago bancario o API-GO la rechaza al registrarla ante
 * SUNAT. El negocio reportó "se rompió el correlativo de facturas" — la
 * causa real (confirmada en el log de producción del 6 de octubre) era
 * justo esto: una Factura de S/ 3,263.88 quedó con un número nunca
 * confirmado porque el formulario no pedía estos datos todavía.
 */
class VentaBancarizacionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datos(array $overrides = []): array
    {
        return $overrides + [
            'fecha' => '2026-10-06', 'fecha_vencimiento' => '2026-10-06',
            'razonsocial' => 'CONTRATISTAS GENERALES ICA S.A.C.', 'monto' => 3263.88,
            'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Transferencia', 'monto' => 3263.88]],
        ];
    }

    public function test_factura_mayor_a_2000_manda_la_bancarizacion_a_api_go(): void
    {
        Http::fake([
            '*/invoices*' => Http::response(['success' => true, 'data' => ['id' => 1, 'serie' => 'F001', 'correlativo' => '00000050']], 200),
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '00000050',
            'bancarizacion_medio_pago' => 'TRAN',
            'bancarizacion_numero_operacion' => 'OP-12345',
            'bancarizacion_banco' => 'BCP',
        ]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/invoices')
                && ($request->data()['bancarizacion']['medio_pago'] ?? null) === 'TRAN'
                && ($request->data()['bancarizacion']['numero_operacion'] ?? null) === 'OP-12345'
                && ($request->data()['bancarizacion']['banco'] ?? null) === 'BCP';
        });
    }

    public function test_boleta_mayor_a_2000_no_manda_bancarizacion(): void
    {
        Http::fake([
            '*/boletas*' => Http::response(['success' => true, 'data' => ['id' => 1, 'serie' => 'B001', 'correlativo' => '00000050']], 200),
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000050',
        ]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/boletas')
                && ! array_key_exists('bancarizacion', $request->data());
        });
    }

    public function test_factura_rechazada_por_bancarizacion_queda_marcada_como_error_visible(): void
    {
        $mensaje = '⚠️ BANCARIZACIÓN OBLIGATORIA: Esta operación supera el umbral de S/ 2,000.00.';

        Http::fake([
            '*/invoices*' => Http::response([
                'success' => false,
                'message' => $mensaje,
                'errors' => ['bancarizacion' => [$mensaje]],
            ], 422),
        ]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '00000051',
        ]));

        $venta = Venta::where('n_comp', '00000051')->firstOrFail();

        // La venta queda guardada local (no se bloquea), pero marcada como
        // rechazada — antes esto solo quedaba en el log, invisible para
        // quien vende, y el número local nunca se corregía al real.
        $this->assertSame('error', $venta->estado_factura);
        $this->assertSame($mensaje, $venta->nota_contadora);

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertSee($mensaje);
    }

    public function test_medios_pago_cae_al_catalogo_de_respaldo_si_api_go_no_responde(): void
    {
        Http::fake(['*/bancarizacion/medios-pago*' => Http::response(null, 500)]);

        $medios = app(ApiGoEmisionService::class)->mediosPagoBancarizacion();

        $this->assertNotEmpty($medios);
        $this->assertContains('TRAN', array_column($medios, 'codigo'));
        $this->assertContains('YAPE', array_column($medios, 'codigo'));
    }

    public function test_medios_pago_usa_el_catalogo_real_cuando_api_go_responde(): void
    {
        Http::fake(['*/bancarizacion/medios-pago*' => Http::response([
            'success' => true,
            'data' => [['codigo' => 'ZZZZ', 'descripcion' => 'Medio de prueba']],
        ], 200)]);

        $medios = app(ApiGoEmisionService::class)->mediosPagoBancarizacion();

        $this->assertSame([['codigo' => 'ZZZZ', 'descripcion' => 'Medio de prueba']], $medios);
    }

    public function test_el_formulario_muestra_el_catalogo_de_medios_de_pago(): void
    {
        Http::fake(['*/bancarizacion/medios-pago*' => Http::response(['success' => true, 'data' => [
            ['codigo' => 'TRAN', 'descripcion' => 'Transferencia bancaria'],
        ]], 200)]);

        $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.create'))
            ->assertOk()
            ->assertSee('tarjetaBancarizacion', false)
            ->assertSee('Transferencia bancaria');
    }
}
