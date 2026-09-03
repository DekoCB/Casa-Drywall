<?php

namespace Tests\Feature;

use App\Models\Venta;
use App\Services\Sunat\ApiGoEmisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Confirma la forma exacta del payload que ApiGoEmisionService envía a
 * API-GO para Nota de Crédito/Débito, contra las reglas reales de
 * StoreCreditNoteRequest/StoreDebitNoteRequest (no se puede probar un envío
 * real sin un documento ya aceptado ante SUNAT de por medio).
 */
class NotaCreditoDebitoTest extends TestCase
{
    use RefreshDatabase;

    public function test_nota_de_credito_envia_el_payload_correcto_a_api_go(): void
    {
        Http::fake([
            '*/credit-notes' => Http::response([
                'success' => true,
                'data' => ['id' => 501, 'numero_completo' => 'FC01-00000001'],
            ], 200),
        ]);

        $origen = Venta::create([
            'fecha' => '2026-09-01',
            'tipcomp' => '01',
            'n_seri' => 'F001',
            'n_comp' => '000001',
            'estado_factura' => 'aceptado',
            'razonsocial' => 'Empresa de Prueba SAC',
            'cliente_nombre' => 'Empresa de Prueba SAC',
            'n_ruc' => '20000000001',
            'cliente_ruc' => '20000000001',
            'cliente_direccion' => 'Av. Prueba 123',
            'cliente_distrito' => 'Pisco',
            'cliente_telefono' => '999000000',
            'cliente_correo' => 'prueba@example.com',
        ]);

        $nota = Venta::create([
            'fecha' => '2026-09-02',
            'tipcomp' => '07',
            'n_seri' => 'FC01',
            'n_comp' => '00000001',
            'venta_origen_id' => $origen->id,
            'cod_motivo' => '06',
            'razonsocial' => $origen->razonsocial,
            'cliente_nombre' => $origen->cliente_nombre,
            'n_ruc' => $origen->n_ruc,
            'cliente_ruc' => $origen->cliente_ruc,
            'cliente_direccion' => $origen->cliente_direccion,
            'cliente_distrito' => $origen->cliente_distrito,
            'baseimp' => 100,
            'igv' => 18,
            'total' => 118,
        ]);
        $nota->setRelation('ventaOrigen', $origen);

        $ok = app(ApiGoEmisionService::class)->crearComprobante($nota);

        $this->assertTrue($ok);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/credit-notes')) {
                return false;
            }

            $data = $request->data();

            return $data['tipo_doc_afectado'] === '01'
                && $data['num_doc_afectado'] === 'F001-000001'
                && $data['cod_motivo'] === '06'
                && $data['des_motivo'] === 'Devolución total'
                && $data['serie'] === 'FC01'
                && $data['moneda'] === 'PEN'
                && $data['client']['numero_documento'] === '20000000001'
                && $data['client']['tipo_documento'] === '6'
                && $data['client']['razon_social'] === 'Empresa de Prueba SAC'
                && $data['client']['direccion'] === 'Av. Prueba 123'
                && is_array($data['detalles']) && count($data['detalles']) === 1
                && ! array_key_exists('metodo_envio', $data);
        });

        $nota->refresh();
        $this->assertSame('credit_note', $nota->api_go_document_type);
        $this->assertSame(501, $nota->api_go_document_id);
        $this->assertSame('registrado', $nota->estado_factura);
    }

    public function test_nota_de_debito_envia_el_payload_correcto_a_api_go(): void
    {
        Http::fake([
            '*/debit-notes' => Http::response([
                'success' => true,
                'data' => ['id' => 777, 'numero_completo' => 'BD01-00000001'],
            ], 200),
        ]);

        $origen = Venta::create([
            'fecha' => '2026-09-01',
            'tipcomp' => '03',
            'n_seri' => 'B001',
            'n_comp' => '000050',
            'estado_factura' => 'aceptado',
            'cliente_nombre' => 'Cliente de Prueba',
            'razonsocial' => 'Cliente de Prueba',
        ]);

        $nota = Venta::create([
            'fecha' => '2026-09-02',
            'tipcomp' => '08',
            'n_seri' => 'BD01',
            'n_comp' => '00000001',
            'venta_origen_id' => $origen->id,
            'cod_motivo' => '01',
            'cliente_nombre' => $origen->cliente_nombre,
            'razonsocial' => $origen->razonsocial,
            'baseimp' => 10,
            'igv' => 1.8,
            'total' => 11.8,
        ]);
        $nota->setRelation('ventaOrigen', $origen);

        app(ApiGoEmisionService::class)->crearComprobante($nota);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/debit-notes')) {
                return false;
            }

            $data = $request->data();

            return $data['tipo_doc_afectado'] === '03'
                && $data['num_doc_afectado'] === 'B001-000050'
                && $data['cod_motivo'] === '01'
                && $data['des_motivo'] === 'Intereses por mora';
        });
    }
}
