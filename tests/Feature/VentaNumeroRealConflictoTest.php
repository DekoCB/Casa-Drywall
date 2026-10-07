<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caso real de producción (7 de octubre): la venta #211 (VILLA MANUELITA
 * HOTEL EIRL.) nunca se registró ante SUNAT — bancarización faltante. Para
 * SUNAT, ese número nunca se consumió de verdad. Cuando la venta #261 (JG
 * ARQUITECTO E.I.R.L, otro cliente, otro día) se registró con éxito, el
 * sistema de facturación le asignó ESE MISMO número real — y
 * `sincronizarNumeroReal()` lo aplicó sin fijarse que ya lo tenía otro
 * comprobante local atascado. Los dos quedaron mostrando F001-001869, sin
 * ninguna explicación visible.
 */
class VentaNumeroRealConflictoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_si_el_numero_real_coincide_con_otro_comprobante_atascado_queda_marcado(): void
    {
        // La venta #211 real: atascada, sin registrar.
        $atascada = Venta::create([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '001869', 'fecha' => '2026-10-06',
            'razonsocial' => 'VILLA MANUELITA HOTEL EIRL.', 'estado' => 'activa',
            'api_go_document_id' => null, 'estado_factura' => 'pendiente',
        ]);

        // SUNAT, al registrar una Factura nueva, le asigna ese mismo número
        // real porque nunca lo consideró usado.
        Http::fake(['*/invoices*' => Http::response([
            'success' => true,
            'data' => ['id' => 18, 'serie' => 'F001', 'correlativo' => '001869'],
        ], 200)]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-10-07', 'fecha_vencimiento' => '2026-10-07', 'tipcomp' => '01',
            'n_seri' => 'F001', 'n_comp' => '001870', 'razonsocial' => 'JG ARQUITECTO E.I.R.L',
            'monto' => 500, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 500]],
        ]);

        $nueva = Venta::where('razonsocial', 'JG ARQUITECTO E.I.R.L')->firstOrFail();

        // El número real SÍ se aplica (es el correcto para esta venta).
        $this->assertSame('F001', $nueva->n_seri);
        $this->assertSame('001869', $nueva->n_comp);
        $this->assertSame('registrado', $nueva->estado_factura);

        // Pero queda marcada con el conflicto, señalando el comprobante viejo.
        $this->assertNotNull($nueva->nota_contadora);
        $this->assertStringContainsString("#{$atascada->id}", $nueva->nota_contadora);
        $this->assertStringContainsString('VILLA MANUELITA HOTEL EIRL.', $nueva->nota_contadora);

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $nueva))
            ->assertOk()
            ->assertSee('Aviso:')
            ->assertSee("comprobante #{$atascada->id}");
    }

    public function test_sin_conflicto_no_hay_ningun_aviso(): void
    {
        Http::fake(['*/invoices*' => Http::response([
            'success' => true,
            'data' => ['id' => 99, 'serie' => 'F001', 'correlativo' => '001900'],
        ], 200)]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-10-07', 'fecha_vencimiento' => '2026-10-07', 'tipcomp' => '01',
            'n_seri' => 'F001', 'n_comp' => '001900', 'razonsocial' => 'Cliente Normal',
            'monto' => 500, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 500]],
        ]);

        $venta = Venta::where('razonsocial', 'Cliente Normal')->firstOrFail();

        $this->assertNull($venta->nota_contadora);

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertDontSee('Aviso:');
    }
}
