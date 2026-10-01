<?php

namespace Tests\Feature;

use App\Models\Cobranza;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\Sunat\ApiGoEmisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * API-GO asigna el correlativo real de Boleta/Factura con su propio
 * contador (independiente del N° Comprobante que la persona escribe en el
 * formulario de "Nueva Venta") — si no coinciden, el comprobante local
 * mostraría un número distinto al que SUNAT aceptó de verdad. Estas pruebas
 * cubren la corrección automática (`crearComprobante()`) y la sugerencia
 * que se le muestra a la persona antes de guardar (`siguienteCorrelativo()`).
 */
class CorrelativoRealSunatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_crearComprobante_corrige_el_numero_local_al_real_de_sunat(): void
    {
        Http::fake([
            '*/boletas' => Http::response([
                'success' => true,
                'data' => ['id' => 42, 'numero_completo' => 'B001-000006', 'serie' => 'B001', 'correlativo' => '000006'],
            ], 201),
        ]);

        $cobranza = new Cobranza([
            'tipo' => 'BV',
            // Número tipeado a mano en el formulario — distinto del que
            // realmente le va a tocar según el contador de API-GO.
            'numero' => 'B001-00000005',
            'fecha_emision' => '2026-10-02',
            'fecha_vencimiento' => '2026-10-02',
            'cliente_nombre' => 'Cliente de Prueba',
            'monto_total' => 118,
            'monto_pagado' => 0,
            'usuario_id' => $this->admin()->id,
        ]);
        $cobranza->recalcularEstado();
        $cobranza->save();

        $venta = Venta::where('cobranza_id', $cobranza->id)->firstOrFail();
        $this->assertSame('B001', $venta->n_seri);
        $this->assertSame('00000005', $venta->n_comp);

        $ok = app(ApiGoEmisionService::class)->crearComprobante($venta);

        $this->assertTrue($ok);

        $venta->refresh();
        $this->assertSame('B001', $venta->n_seri);
        $this->assertSame('000006', $venta->n_comp, 'El N° Comprobante local debe corregirse al correlativo real que asignó API-GO.');

        $cobranza->refresh();
        $this->assertSame('B001-000006', $cobranza->numero, 'La Cobranza enlazada también debe quedar con el número real.');
    }

    public function test_crearComprobante_no_toca_nada_si_api_go_no_informa_serie_ni_correlativo(): void
    {
        Http::fake([
            '*/invoices' => Http::response([
                'success' => true,
                'data' => ['id' => 99, 'numero_completo' => 'F001-00000010'],
            ], 201),
        ]);

        $venta = Venta::create([
            'fecha' => '2026-10-02',
            'tipcomp' => '01',
            'n_seri' => 'F001',
            'n_comp' => '00000010',
            'razonsocial' => 'Empresa de Prueba SAC',
            'cliente_nombre' => 'Empresa de Prueba SAC',
            'n_ruc' => '20000000001',
            'cliente_ruc' => '20000000001',
            'total' => 118,
        ]);

        app(ApiGoEmisionService::class)->crearComprobante($venta);

        $venta->refresh();
        $this->assertSame('F001', $venta->n_seri);
        $this->assertSame('00000010', $venta->n_comp);
    }

    public function test_siguienteCorrelativo_devuelve_el_proximo_numero_formateado(): void
    {
        Http::fake([
            '*/branches/*/correlatives' => Http::response([
                'success' => true,
                'data' => [
                    'correlatives' => [
                        ['tipo_documento' => '03', 'serie' => 'B001', 'correlativo_actual' => 1928],
                        ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo_actual' => 1855],
                    ],
                ],
            ], 200),
        ]);

        $servicio = app(ApiGoEmisionService::class);

        $this->assertSame('001929', $servicio->siguienteCorrelativo('03', 'B001'));
        $this->assertSame('001856', $servicio->siguienteCorrelativo('01', 'F001'));
        // Serie sin correlativo configurado en API-GO: ninguna sugerencia,
        // el formulario cae al campo editable de siempre.
        $this->assertNull($servicio->siguienteCorrelativo('03', 'B002'));
    }

    public function test_siguienteCorrelativo_devuelve_null_si_api_go_no_responde(): void
    {
        Http::fake(['*/branches/*/correlatives' => Http::response(null, 500)]);

        $this->assertNull(app(ApiGoEmisionService::class)->siguienteCorrelativo('03', 'B001'));
    }

    public function test_createFactura_pasa_la_sugerencia_de_sunat_al_formulario(): void
    {
        Http::fake([
            '*/branches/*/correlatives' => Http::response([
                'success' => true,
                'data' => [
                    'correlatives' => [
                        ['tipo_documento' => '03', 'serie' => 'B001', 'correlativo_actual' => 1928],
                    ],
                ],
            ], 200),
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.create'));

        $respuesta->assertOk();
        $this->assertSame('001929', $respuesta->viewData('correlativosInternos')['03']);
        $this->assertNull($respuesta->viewData('correlativosInternos')['01']);
    }
}
