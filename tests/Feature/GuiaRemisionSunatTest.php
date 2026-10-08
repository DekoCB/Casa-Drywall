<?php

namespace Tests\Feature;

use App\Models\GuiaRemision;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guía de Remisión nunca estuvo conectada a SUNAT — era un papel interno.
 * Mismo patrón que ya probamos para Boleta/Factura: el registro en API-GO
 * nunca bloquea el guardado local, y si falla el motivo queda visible.
 * A diferencia de Boleta/Factura, el endpoint de Guías exige un
 * `destinatario_id` ya resuelto (no acepta el cliente embebido inline).
 */
class GuiaRemisionSunatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosPublico(array $overrides = []): array
    {
        return $overrides + [
            'fecha' => '2026-10-08', 'fecha_traslado' => '2026-10-09',
            'cod_traslado' => '01', 'mod_traslado' => '01',
            'cliente_nombre' => 'Contratistas Generales Ica S.A.C.', 'cliente_ruc' => '20601111116',
            'punto_partida' => 'Jr. Lima 123', 'partida_ubigeo' => '120101',
            'punto_llegada' => 'Av. Grau 456', 'llegada_ubigeo' => '150101',
            'empresa_transporte' => 'Transportes Veloz', 'transportista_ruc' => '20555555551',
            'peso_total' => 150.5, 'bultos' => 3,
        ];
    }

    private function fakeClienteYRegistroExitoso(): void
    {
        Http::fake([
            '*/clients/search-by-document*' => Http::response(['success' => true, 'data' => ['id' => 77]], 200),
            '*/dispatch-guides' => Http::response([
                'success' => true,
                'data' => ['id' => 55, 'serie' => 'T001', 'correlativo' => '00000010'],
            ], 200),
        ]);
    }

    public function test_registro_exitoso_manda_el_destinatario_ya_resuelto(): void
    {
        $this->fakeClienteYRegistroExitoso();

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $this->datosPublico());

        $guia = GuiaRemision::firstOrFail();

        $this->assertSame(55, $guia->api_go_document_id);
        $this->assertSame('registrado', $guia->estado_sunat);
        $this->assertSame('T001-00000010', $guia->numero_sunat);
        // El número local (GR-...) nunca se pisa con el real, a propósito.
        $this->assertStringStartsWith('GR-', $guia->numero_guia);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/dispatch-guides')
            && ($r->data()['destinatario_id'] ?? null) === 77
            && ($r->data()['cod_traslado'] ?? null) === '01'
            && ($r->data()['transportista_razon_social'] ?? null) === 'Transportes Veloz');
    }

    public function test_si_el_cliente_no_existe_en_api_go_se_crea(): void
    {
        Http::fake([
            '*/clients/search-by-document*' => Http::response(['success' => false, 'message' => 'Cliente no encontrado'], 404),
            '*/clients' => Http::response(['success' => true, 'data' => ['id' => 88]], 201),
            '*/dispatch-guides' => Http::response([
                'success' => true,
                'data' => ['id' => 55, 'serie' => 'T001', 'correlativo' => '00000010'],
            ], 200),
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $this->datosPublico());

        $this->assertSame(55, GuiaRemision::firstOrFail()->api_go_document_id);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/clients') && ! str_contains($r->url(), 'search-by-document')
            && ($r->data()['numero_documento'] ?? null) === '20601111116');
    }

    public function test_fallo_de_registro_no_bloquea_el_guardado_local(): void
    {
        Http::fake([
            '*/clients/search-by-document*' => Http::response(['success' => true, 'data' => ['id' => 77]], 200),
            '*/dispatch-guides' => Http::response(['success' => false, 'message' => 'Ubigeo de partida inválido.'], 422),
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $this->datosPublico())
            ->assertRedirect();

        $guia = GuiaRemision::firstOrFail();

        $this->assertNull($guia->api_go_document_id);
        $this->assertSame('error', $guia->estado_sunat);
        $this->assertSame('Ubigeo de partida inválido.', $guia->nota_sunat);
    }

    public function test_reintentar_registra_una_guia_que_nunca_se_registro(): void
    {
        $guia = GuiaRemision::create($this->datosPublico() + [
            'numero_guia' => 'GR-20261008-0001', 'motivo_traslado' => 'Venta', 'estado' => 'emitida',
        ]);

        $this->fakeClienteYRegistroExitoso();

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.reintentar-sunat', $guia))
            ->assertRedirect();

        $this->assertSame(55, $guia->fresh()->api_go_document_id);
    }

    public function test_no_se_puede_reintentar_una_guia_ya_registrada(): void
    {
        $guia = GuiaRemision::create($this->datosPublico() + [
            'numero_guia' => 'GR-20261008-0001', 'motivo_traslado' => 'Venta', 'estado' => 'emitida',
            'api_go_document_id' => 10, 'estado_sunat' => 'registrado',
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.reintentar-sunat', $guia))
            ->assertNotFound();
    }

    public function test_modalidad_publica_exige_datos_del_transportista(): void
    {
        $datos = $this->datosPublico(['empresa_transporte' => null, 'transportista_ruc' => null]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $datos)
            ->assertSessionHasErrors(['empresa_transporte', 'transportista_ruc']);

        $this->assertSame(0, GuiaRemision::count());
    }

    public function test_modalidad_privada_exige_conductor_y_vehiculo(): void
    {
        $datos = $this->datosPublico([
            'mod_traslado' => '02',
            'empresa_transporte' => null, 'transportista_ruc' => null,
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $datos)
            ->assertSessionHasErrors(['placa_vehiculo', 'conductor_nombre', 'conductor_dni', 'licencia_conductor']);

        $this->assertSame(0, GuiaRemision::count());
    }

    public function test_modalidad_privada_completa_registra_correctamente(): void
    {
        $this->fakeClienteYRegistroExitoso();

        $datos = $this->datosPublico([
            'mod_traslado' => '02',
            'empresa_transporte' => null, 'transportista_ruc' => null,
            'placa_vehiculo' => 'ABC-123', 'conductor_nombre' => 'Juan Pérez Ramos',
            'conductor_dni' => '45678912', 'licencia_conductor' => 'Q12345678',
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $datos);

        $this->assertSame(55, GuiaRemision::firstOrFail()->api_go_document_id);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/dispatch-guides')
            && ($r->data()['conductor_nombres'] ?? null) === 'Juan'
            && ($r->data()['conductor_apellidos'] ?? null) === 'Pérez Ramos'
            && ($r->data()['vehiculo_placa'] ?? null) === 'ABC-123');
    }

    public function test_sin_ruc_del_destinatario_no_pasa_la_validacion(): void
    {
        $datos = $this->datosPublico(['cliente_ruc' => null]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.guias.store'), $datos)
            ->assertSessionHasErrors('cliente_ruc');
    }
}
