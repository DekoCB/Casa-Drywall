<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Antes, un comprobante que nunca llegaba a registrarse en API-GO (ej. una
 * Factura rechazada por bancarización faltante, caso real del 6 de octubre
 * — venta #211, S/ 3,263.88) se quedaba sin ninguna forma de reintentarse
 * desde el sistema: "Enviar a SUNAT" solo reenvía algo YA registrado
 * localmente, y este comprobante nunca llegó a esa etapa. Este botón nuevo
 * cubre justo ese hueco.
 */
class VentaReintentarSunatTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_BANCARIZACION = '⚠️ BANCARIZACIÓN OBLIGATORIA: Esta operación supera el umbral de S/ 2,000.00.';

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    /**
     * Crea una Factura cuyo registro en API-GO falla (bancarización
     * faltante), igual que el caso real — asume que quien llama ya dejó
     * `Http::fake()` listo para esta primera petición a `/invoices`.
     */
    private function crearFacturaRechazada(Usuario $admin): Venta
    {
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-10-06', 'fecha_vencimiento' => '2026-10-06', 'tipcomp' => '01',
            'n_seri' => 'F001', 'n_comp' => '00000211', 'razonsocial' => 'CONTRATISTAS GENERALES ICA S.A.C.',
            'monto' => 3263.88, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Transferencia', 'monto' => 3263.88]],
        ]);

        return Venta::where('n_comp', '00000211')->firstOrFail();
    }

    public function test_reintentar_con_bancarizacion_completa_registra_el_comprobante(): void
    {
        // Misma URL (`/invoices`), dos respuestas en orden: la creación
        // original falla, el reintento con los datos completos funciona.
        Http::fake(['*/invoices*' => Http::sequence()
            ->push(['success' => false, 'message' => self::MENSAJE_BANCARIZACION], 422)
            ->push(['success' => true, 'data' => ['id' => 99, 'serie' => 'F001', 'correlativo' => '00000211']], 200),
        ]);

        $admin = $this->admin();
        $venta = $this->crearFacturaRechazada($admin);

        $this->assertNull($venta->api_go_document_id);

        $this->actingAs($admin, 'web')->post(route('admin.ventas.reintentar-sunat', $venta), [
            'bancarizacion_medio_pago' => 'TRAN',
            'bancarizacion_numero_operacion' => 'OP-99999',
        ])->assertRedirect();

        $venta->refresh();
        $this->assertSame(99, $venta->api_go_document_id);
        $this->assertSame('registrado', $venta->estado_factura);
        $this->assertSame('TRAN', $venta->bancarizacion_medio_pago);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/invoices')
            && ($r->data()['bancarizacion']['medio_pago'] ?? null) === 'TRAN');
    }

    public function test_reintentar_sin_completar_bancarizacion_vuelve_a_fallar(): void
    {
        Http::fake(['*/invoices*' => Http::sequence()
            ->push(['success' => false, 'message' => self::MENSAJE_BANCARIZACION], 422)
            ->push(['success' => false, 'message' => 'Sigue faltando la bancarización.'], 422),
        ]);

        $admin = $this->admin();
        $venta = $this->crearFacturaRechazada($admin);

        $this->actingAs($admin, 'web')->post(route('admin.ventas.reintentar-sunat', $venta), [])
            ->assertRedirect();

        $venta->refresh();
        $this->assertNull($venta->api_go_document_id);
        $this->assertSame('error', $venta->estado_factura);
        $this->assertSame('Sigue faltando la bancarización.', $venta->nota_contadora);
    }

    public function test_no_se_puede_reintentar_un_comprobante_ya_registrado(): void
    {
        $admin = $this->admin();
        $venta = Venta::create([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '00000300', 'fecha' => '2026-10-06',
            'razonsocial' => 'Cliente', 'estado' => 'activa',
            'api_go_document_id' => 50, 'api_go_document_type' => 'invoice', 'estado_factura' => 'registrado',
        ]);

        $this->actingAs($admin, 'web')->post(route('admin.ventas.reintentar-sunat', $venta))
            ->assertNotFound();
    }

    public function test_el_boton_reintentar_aparece_en_el_comprobante_sin_registrar(): void
    {
        Http::fake([
            '*/invoices*' => Http::response(['success' => false, 'message' => self::MENSAJE_BANCARIZACION], 422),
            '*/bancarizacion/medios-pago*' => Http::response(['success' => true, 'data' => [
                ['codigo' => 'TRAN', 'descripcion' => 'Transferencia bancaria'],
            ]], 200),
        ]);

        $admin = $this->admin();
        $venta = $this->crearFacturaRechazada($admin);

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertSee('Reintentar registro SUNAT')
            ->assertDontSee('Enviar a SUNAT');
    }
}
