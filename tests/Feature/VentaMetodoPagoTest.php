<?php

namespace Tests\Feature;

use App\Models\MetodoPago;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nota de Venta, Boleta y Factura ahora piden el medio de pago (Efectivo,
 * Yape, Plin, Transferencia bancaria, etc. — el mismo catálogo real de
 * `metodos_pago`, antes solo usado por el POS con sus botones fijos y sin
 * conectar a este formulario). Cotización queda exenta: todavía no hay un
 * pago real que registrar, es solo un presupuesto.
 */
class VentaMetodoPagoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosNota(array $sobrescribe = []): array
    {
        return array_merge([
            'fecha' => '2026-09-16',
            'fecha_vencimiento' => '2026-09-16',
            'tipcomp' => 'NV',
            'n_seri' => 'NV01',
            'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba',
            'monto' => 100,
            'tipo_operacion' => 'gravada',
            'precios_incluyen_igv' => 1,
        ], $sobrescribe);
    }

    public function test_nota_de_venta_sin_medio_de_pago_es_rechazada(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota());

        $respuesta->assertSessionHasErrors('metodo_pago');
        $this->assertSame(0, Venta::count());
    }

    public function test_boleta_sin_medio_de_pago_es_rechazada(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'tipcomp' => '03', 'n_seri' => 'B001',
        ]));

        $respuesta->assertSessionHasErrors('metodo_pago');
        $this->assertSame(0, Venta::count());
    }

    public function test_nota_de_venta_con_medio_de_pago_se_guarda(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'metodo_pago' => 'Yape',
        ]));

        $respuesta->assertRedirect();

        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();
        $this->assertSame('Yape', $venta->metodo_pago);
    }

    public function test_cotizacion_no_exige_medio_de_pago(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'tipcomp' => 'COT', 'n_seri' => 'CT01',
        ]));

        $respuesta->assertRedirect();
        $respuesta->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Venta::count());
    }

    /**
     * El <select> de Medio de pago no va alfabético — el negocio pidió un
     * orden fijo: Efectivo, Yape, Plin, Transferencia bancaria, Tarjeta,
     * Depósito bancario (los últimos dos, sin posición pedida, van al final).
     */
    public function test_el_select_de_medio_de_pago_respeta_el_orden_fijo_pedido(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.create'));

        $respuesta->assertOk();

        $html = $respuesta->getContent();
        $inicioSelect = strpos($html, 'id="f-metodo-pago"');
        $finSelect = strpos($html, '</select>', $inicioSelect);
        $bloqueSelect = substr($html, $inicioSelect, $finSelect - $inicioSelect);

        $posiciones = [];
        foreach (['Efectivo', 'Yape', 'Plin', 'Transferencia bancaria', 'Tarjeta', 'Depósito bancario'] as $nombre) {
            $posiciones[$nombre] = strpos($bloqueSelect, $nombre);
        }

        $this->assertSame(
            ['Efectivo', 'Yape', 'Plin', 'Transferencia bancaria', 'Tarjeta', 'Depósito bancario'],
            collect($posiciones)->sort()->keys()->all()
        );
    }

    public function test_pagina_de_edicion_muestra_el_catalogo_de_metodos_de_pago(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'metodo_pago' => 'Efectivo',
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.edit', $venta));

        $respuesta->assertOk();
        $respuesta->assertSee('Medio de pago', false);
        $respuesta->assertSee('Yape', false);
        $respuesta->assertSee('Plin', false);
    }

    public function test_editar_sin_medio_de_pago_es_rechazado(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'metodo_pago' => 'Efectivo',
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ]);

        $respuesta->assertSessionHasErrors('metodo_pago');
        $this->assertSame('Efectivo', $venta->fresh()->metodo_pago);
    }

    public function test_editar_puede_cambiar_el_medio_de_pago(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'metodo_pago' => 'Efectivo',
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'metodo_pago' => 'Transferencia bancaria',
        ]);

        $respuesta->assertRedirect();
        $this->assertSame('Transferencia bancaria', $venta->fresh()->metodo_pago);
    }

    /** Uno nuevo, agregado desde Configuración y no contemplado en el orden fijo, cae al final sin romper nada. */
    public function test_metodo_pago_no_contemplado_en_el_orden_cae_al_final(): void
    {
        MetodoPago::create(['nombre' => 'QR Interoperable', 'activo' => true]);

        $orden = MetodoPago::activosOrdenados()->pluck('nombre')->all();

        $this->assertSame('QR Interoperable', end($orden));
    }

    public function test_editar_cotizacion_no_exige_medio_de_pago(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'tipcomp' => 'COT', 'n_seri' => 'CT01',
        ]));
        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionDoesntHaveErrors();
    }
}
