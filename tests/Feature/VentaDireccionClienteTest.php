<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dirección del cliente, opcional, en Cotización/Nota de Venta/Boleta/
 * Factura — antes `cliente_direccion` solo se llenaba si el nombre escrito
 * coincidía con una ficha de Cliente ya guardada (`fichaDelCliente()`); no
 * había forma de escribirla a mano para un cliente nuevo o sin ficha.
 */
class VentaDireccionClienteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosBase(): array
    {
        return [
            'fecha' => '2026-10-02', 'fecha_vencimiento' => '2026-10-02', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ];
    }

    public function test_la_direccion_escrita_a_mano_se_guarda(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'direccion' => 'Av. Los Próceres 123, Pisco',
        ])->assertRedirect();

        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();
        $this->assertSame('Av. Los Próceres 123, Pisco', $venta->cliente_direccion);
    }

    public function test_es_opcional_y_no_bloquea_la_venta(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase())
            ->assertRedirect();

        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();
        $this->assertNull($venta->cliente_direccion);
    }

    /** Sin escribir nada, cae a la dirección de la ficha del cliente elegido. */
    public function test_si_se_deja_en_blanco_usa_la_direccion_de_la_ficha_del_cliente(): void
    {
        $cliente = Cliente::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Cliente de Prueba', 'direccion' => 'Jr. Lima 456',
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'cliente_id' => $cliente->id,
        ])->assertRedirect();

        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();
        $this->assertSame('Jr. Lima 456', $venta->cliente_direccion);
    }

    /** Lo escrito a mano manda incluso si hay una ficha de cliente con otra dirección. */
    public function test_lo_escrito_a_mano_gana_sobre_la_direccion_de_la_ficha(): void
    {
        $cliente = Cliente::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Cliente de Prueba', 'direccion' => 'Jr. Lima 456',
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'cliente_id' => $cliente->id, 'direccion' => 'Dirección de entrega distinta 789',
        ])->assertRedirect();

        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();
        $this->assertSame('Dirección de entrega distinta 789', $venta->cliente_direccion);
    }

    public function test_el_formulario_de_alta_muestra_el_campo_direccion(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.create'));

        $respuesta->assertOk();
        $respuesta->assertSee('name="direccion"', false);
    }

    /** El mapa (Google Maps embed, sin API key) vive dentro del mismo card Cliente. */
    public function test_el_formulario_muestra_el_mapa_de_la_direccion(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.create'));

        $respuesta->assertOk();
        $respuesta->assertSee('id="f-direccion-mapa"', false);
        $respuesta->assertSee('google.com/maps', false);
    }

    /**
     * Distrito/Provincia/Departamento: sin campo visible todavía (la
     * búsqueda de RUC los llena solos por JS en un input oculto) — el
     * negocio pidió esto explícitamente porque la búsqueda de RUC ya
     * trae esos datos y no se estaban guardando.
     */
    public function test_distrito_provincia_departamento_se_guardan_si_llegan(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'direccion' => 'Av. Los Próceres 123', 'distrito' => 'San Isidro',
            'provincia' => 'Lima', 'departamento' => 'Lima',
        ])->assertRedirect();

        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();
        $this->assertSame('San Isidro', $venta->cliente_distrito);
        $this->assertSame('Lima', $venta->cliente_provincia);
        $this->assertSame('Lima', $venta->cliente_departamento);
    }

    public function test_distrito_provincia_departamento_caen_a_la_ficha_del_cliente_si_faltan(): void
    {
        $cliente = Cliente::create([
            'tipo_documento' => 'RUC', 'numero_documento' => '20123456789',
            'nombres' => 'Constructora Andina S.A.C.', 'direccion' => 'Jr. Titanita 160',
            'distrito' => 'Comas', 'provincia' => 'Lima', 'departamento' => 'Lima',
        ]);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosBase() + [
            'cliente_id' => $cliente->id,
        ])->assertRedirect();

        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();
        $this->assertSame('Comas', $venta->cliente_distrito);
        $this->assertSame('Lima', $venta->cliente_provincia);
        $this->assertSame('Lima', $venta->cliente_departamento);
    }

    public function test_la_busqueda_de_ruc_devuelve_direccion_y_ubigeo_para_un_cliente_ya_registrado(): void
    {
        Cliente::create([
            'tipo_documento' => 'RUC', 'numero_documento' => '20601111111',
            'nombres' => 'Servicio y Construcciones A&L S.A.C.', 'direccion' => 'Jr. Titanita 160',
            'distrito' => 'Comas', 'provincia' => 'Lima', 'departamento' => 'Lima',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->getJson(route('admin.documentos.buscar', ['tipo' => 'ruc', 'numero' => '20601111111']));

        $respuesta->assertOk();
        $respuesta->assertJsonFragment([
            'direccion' => 'Jr. Titanita 160',
            'distrito' => 'Comas',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
        ]);
    }
}
