<?php

namespace Tests\Feature;

use App\Models\ActivoFijo;
use App\Models\CotizacionProveedor;
use App\Models\Egreso;
use App\Models\LiquidacionCompra;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo Compras: Activos Fijos (con su Egreso automático), Solicitud de
 * Cotización a proveedor, Liquidación de Compra (registro interno, no
 * SUNAT), y el filtro `?tipo=` nuevo de Egresos ("Gastos diversos").
 */
class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::create(['ruc' => '20123456789', 'razon_social' => 'Distribuidora XYZ', 'email' => 'compras@xyz.pe']);
    }

    public function test_comprar_activo_fijo_genera_el_egreso_correspondiente(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.activos-fijos.store'), [
            'nombre' => 'Camioneta de reparto',
            'categoria' => 'Vehículo',
            'fecha_compra' => '2026-09-01',
            'costo' => 45000,
            'estado' => 'activo',
        ])->assertRedirect();

        $activo = ActivoFijo::where('nombre', 'Camioneta de reparto')->firstOrFail();
        $this->assertSame(45000.0, (float) $activo->costo);

        $egreso = Egreso::where('origen', 'activo_fijo')->where('origen_id', $activo->id)->first();
        $this->assertNotNull($egreso);
        $this->assertSame(45000.0, (float) $egreso->monto);
        $this->assertSame('Activo fijo', $egreso->tipo);
    }

    public function test_eliminar_activo_fijo_borra_tambien_su_egreso(): void
    {
        $admin = $this->actingAs($this->admin(), 'web');
        $admin->post(route('admin.activos-fijos.store'), [
            'nombre' => 'Taladro industrial', 'fecha_compra' => '2026-09-01', 'costo' => 800, 'estado' => 'activo',
        ]);
        $activo = ActivoFijo::firstOrFail();

        $admin->delete(route('admin.activos-fijos.destroy', $activo))->assertRedirect();

        $this->assertDatabaseMissing('activos_fijos', ['id' => $activo->id]);
        $this->assertDatabaseMissing('egresos', ['origen' => 'activo_fijo', 'origen_id' => $activo->id]);
    }

    public function test_solicitud_de_cotizacion_numera_correlativo_y_guarda_los_items(): void
    {
        $proveedor = $this->proveedor();

        $this->actingAs($this->admin(), 'web')->post(route('admin.cotizaciones-proveedor.store'), [
            'fecha' => '2026-09-01',
            'proveedor_id' => $proveedor->id,
            'productos' => "50 planchas de drywall 1/2\n20 parantes galvanizados",
            'estado' => 'enviada',
        ])->assertRedirect();

        $cotizacion = CotizacionProveedor::firstOrFail();
        $this->assertStringStartsWith('SC-', $cotizacion->numero);
        $this->assertCount(2, $cotizacion->productos);
    }

    public function test_enviar_solicitud_de_cotizacion_falla_amablemente_sin_correo_de_proveedor(): void
    {
        $proveedor = Proveedor::create(['ruc' => '20123456780', 'razon_social' => 'Proveedor sin correo']);
        $cotizacion = CotizacionProveedor::create([
            'numero' => 'SC-20260901-0001', 'fecha' => '2026-09-01', 'proveedor_id' => $proveedor->id,
            'productos' => [], 'estado' => 'enviada',
        ]);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.cotizaciones-proveedor.enviar', $cotizacion))
            ->assertRedirect();

        $this->assertTrue(session()->has('error'));
    }

    public function test_liquidacion_de_compra_se_registra_y_su_comprobante_avisa_que_no_es_sunat(): void
    {
        $admin = $this->actingAs($this->admin(), 'web');

        $admin->post(route('admin.liquidaciones-compra.store'), [
            'fecha' => '2026-09-01',
            'vendedor_nombre' => 'Juan Pérez (informal)',
            'vendedor_documento' => '12345678',
            'productos' => "Arena gruesa\nPiedra chancada",
            'total' => 350,
        ])->assertRedirect();

        $liquidacion = LiquidacionCompra::firstOrFail();
        $this->assertStringStartsWith('LC-', $liquidacion->numero);

        $comprobante = $admin->get(route('admin.liquidaciones-compra.comprobante', $liquidacion));
        $comprobante->assertOk();
        $comprobante->assertSee('NO ES UN COMPROBANTE ELECTRÓNICO SUNAT', false);
    }

    public function test_filtro_tipo_de_egresos_aisla_gastos_diversos(): void
    {
        Egreso::create(['fecha' => '2026-09-01', 'tipo' => 'diversos', 'monto' => 120, 'origen' => 'manual']);
        Egreso::create(['fecha' => '2026-09-01', 'tipo' => 'flete', 'monto' => 80, 'origen' => 'manual']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.egresos.index', ['mes' => 9, 'anio' => 2026, 'tipo' => 'diversos']));

        $respuesta->assertOk();
        $tipos = $respuesta->viewData('egresos')->pluck('tipo');
        $this->assertSame(['diversos'], $tipos->unique()->values()->all());
    }

    public function test_sidebar_de_compras_renderiza_los_8_items(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Listado de compras');
        $respuesta->assertSee('Gastos diversos');
        $respuesta->assertSee('Solicitar cotización');
        $respuesta->assertSee('Activos fijos');
        $respuesta->assertSee('Comprar activo fijo');
        $respuesta->assertSee('Liquidación de compra');
        $respuesta->assertSee('Orden de compra');
    }
}
