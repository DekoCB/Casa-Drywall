<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ventas registra al cliente al emitir el comprobante: quien compra con un
 * DNI/RUC nuevo queda en Clientes y en la siguiente compra se encuentra
 * solo, sin darlo de alta aparte. La búsqueda de DNI también lo halla en
 * comprobantes viejos, sin depender de RENIEC.
 */
class VentaRegistraClienteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'fecha' => '2026-10-05', 'fecha_vencimiento' => '2026-10-05', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'JUAN PEREZ RAMOS',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ];
    }

    public function test_un_dni_nuevo_queda_registrado_como_cliente_y_enlazado(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'n_ruc' => '46027897', 'direccion' => 'Jr. Lima 456',
        ]))->assertRedirect();

        $cliente = Cliente::where('numero_documento', '46027897')->firstOrFail();
        $this->assertSame('DNI', $cliente->tipo_documento);
        $this->assertSame('JUAN PEREZ RAMOS', $cliente->nombres);
        $this->assertSame('Jr. Lima 456', $cliente->direccion);
        $this->assertSame($cliente->id, Venta::where('n_seri', 'CT01')->value('cliente_id'));
    }

    public function test_un_ruc_nuevo_queda_registrado_con_razon_social(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'n_ruc' => '20131312955', 'razonsocial' => 'EMPRESA SAC',
        ]))->assertRedirect();

        $cliente = Cliente::where('numero_documento', '20131312955')->firstOrFail();
        $this->assertSame('RUC', $cliente->tipo_documento);
        $this->assertSame('EMPRESA SAC', $cliente->nombre_empresa);
    }

    public function test_un_cliente_ya_registrado_no_se_duplica(): void
    {
        $existente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '46027897', 'nombres' => 'JUAN PEREZ RAMOS']);

        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'n_ruc' => '46027897',
        ]))->assertRedirect();

        $this->assertSame(1, Cliente::count());
        $this->assertSame($existente->id, Venta::where('n_seri', 'CT01')->value('cliente_id'));
    }

    public function test_sin_documento_valido_no_crea_ficha(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datos([
            'n_ruc' => '123', 'razonsocial' => 'Cliente Varios',
        ]))->assertRedirect();

        $this->assertSame(0, Cliente::count());
    }

    public function test_buscar_dni_encuentra_al_cliente_registrado_sin_consultar_reniec(): void
    {
        Http::fake();
        $cliente = Cliente::create(['tipo_documento' => 'DNI', 'numero_documento' => '46027897', 'nombres' => 'JUAN PEREZ RAMOS', 'direccion' => 'Jr. Lima 456']);

        $this->actingAs($this->admin(), 'web')->getJson(route('admin.documentos.buscar', ['dni', '46027897']))
            ->assertOk()
            ->assertJsonPath('origen', 'local')
            ->assertJsonPath('datos.cliente_id', $cliente->id)
            ->assertJsonPath('datos.nombre_completo', 'JUAN PEREZ RAMOS')
            ->assertJsonPath('datos.direccion', 'Jr. Lima 456');

        Http::assertNothingSent();
    }

    public function test_buscar_dni_encuentra_al_cliente_en_una_venta_anterior_sin_ficha(): void
    {
        Http::fake();
        Venta::create([
            'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000010', 'fecha' => '2026-10-03',
            'n_ruc' => '46027897', 'razonsocial' => 'JUAN PEREZ RAMOS', 'monto' => 50, 'estado' => 'activa',
        ]);

        $this->actingAs($this->admin(), 'web')->getJson(route('admin.documentos.buscar', ['dni', '46027897']))
            ->assertOk()
            ->assertJsonPath('origen', 'local')
            ->assertJsonPath('datos.nombre_completo', 'JUAN PEREZ RAMOS');

        Http::assertNothingSent();
    }

    public function test_buscar_dni_reintenta_cuando_reniec_limita_las_consultas(): void
    {
        Http::fake([
            'api.apis.net.pe/*' => Http::sequence()
                ->push('Too Many Requests', 429)
                ->push(['nombres' => 'JUAN', 'apellidoPaterno' => 'PEREZ', 'apellidoMaterno' => 'RAMOS']),
        ]);

        $this->actingAs($this->admin(), 'web')->getJson(route('admin.documentos.buscar', ['dni', '46027897']))
            ->assertOk()
            ->assertJsonPath('origen', 'reniec')
            ->assertJsonPath('datos.nombre_completo', 'JUAN PEREZ RAMOS');
    }
}
