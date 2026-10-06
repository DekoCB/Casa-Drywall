<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Código QR de Boleta/Factura: el negocio pidió que ambas lo tengan para la
 * conformidad del cliente. Solo aplica a comprobantes reales ante SUNAT
 * ('01'/'03') — Cotización y Nota de Venta no son comprobantes fiscales, no
 * les corresponde. Se genera con un servicio externo sin API key y se
 * cachea en disco por venta (ver CodigoQrService) para no volver a pedirlo
 * en cada vista.
 */
class VentaCodigoQrTest extends TestCase
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
            'razonsocial' => 'JUAN PEREZ RAMOS', 'monto' => 118,
            'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 118]],
        ];
    }

    public function test_la_boleta_muestra_el_codigo_qr(): void
    {
        Storage::fake('public');
        Http::fake(['api.qrserver.com/*' => Http::response('contenido-png-falso', 200)]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000123',
        ]));

        $venta = Venta::where('tipcomp', '03')->firstOrFail();

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertSee(route('admin.ventas.qr', $venta), false);
    }

    public function test_la_factura_tambien_muestra_el_codigo_qr(): void
    {
        Storage::fake('public');
        Http::fake(['api.qrserver.com/*' => Http::response('contenido-png-falso', 200)]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '00000045',
        ]));

        $venta = Venta::where('tipcomp', '01')->firstOrFail();

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertSee(route('admin.ventas.qr', $venta), false);
    }

    public function test_la_nota_de_venta_no_muestra_codigo_qr(): void
    {
        Storage::fake('public');
        Http::fake();

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000001',
        ]));

        $venta = Venta::where('tipcomp', 'NV')->firstOrFail();

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertDontSee(route('admin.ventas.qr', $venta), false);

        Http::assertNothingSent();
    }

    public function test_si_el_servicio_externo_falla_la_pagina_no_se_rompe(): void
    {
        Storage::fake('public');
        Http::fake(['api.qrserver.com/*' => Http::response('', 500)]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000124',
        ]));

        $venta = Venta::where('tipcomp', '03')->firstOrFail();

        $this->actingAs($admin, 'web')->get(route('admin.ventas.comprobante', $venta))
            ->assertOk()
            ->assertDontSee(route('admin.ventas.qr', $venta), false);
    }

    public function test_la_imagen_se_sirve_y_se_cachea_sin_volver_a_pedirla(): void
    {
        Storage::fake('public');
        Http::fake(['api.qrserver.com/*' => Http::response('contenido-png-falso', 200)]);

        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000125',
        ]));

        $venta = Venta::where('tipcomp', '03')->firstOrFail();

        $this->actingAs($admin, 'web')->get(route('admin.ventas.qr', $venta))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Pedirla otra vez no debe volver a golpear el servicio externo
        // (la venta ya dispara su propia emisión real a API-GO al crearse,
        // así que se filtra solo lo que le pegó a qrserver.com).
        $this->actingAs($admin, 'web')->get(route('admin.ventas.qr', $venta))->assertOk();

        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), 'qrserver.com')));
    }
}
