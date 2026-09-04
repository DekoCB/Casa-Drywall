<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submenú de Ventas reordenado: cada tipo (Cotizaciones/Notas de Venta/
 * Boletas/Facturas) tiene su propia lista filtrada por `tipcomp`, y desde
 * una Cotización se puede generar la venta real precargando cliente e
 * ítems en el formulario de alta.
 */
class VentaGenerarDesdeCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_filtro_tipcomp_muestra_solo_ese_tipo(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001']);
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000002']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.index', ['tipcomp' => 'COT']));

        $ids = collect($respuesta->viewData('grupos'))->flatten()->pluck('n_comp');
        $this->assertContains('00000001', $ids);
        $this->assertNotContains('00000002', $ids);
    }

    public function test_formulario_precarga_cliente_e_items_desde_la_cotizacion(): void
    {
        $cot = Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba', 'n_ruc' => '12345678',
        ]);
        VentaDetalle::create([
            'venta_id' => $cot->id, 'prod_codigo' => 'P001', 'prod_nombre' => 'Plancha de Drywall',
            'cantidad' => 3, 'precio_unitario' => 25.5, 'subtotal' => 76.5,
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.factura.create', ['tipo' => '03', 'desde' => $cot->id]));

        $respuesta->assertOk();
        $origen = $respuesta->viewData('origen');

        $this->assertSame('Cliente de Prueba', $origen['razonsocial']);
        $this->assertSame('12345678', $origen['n_ruc']);
        $this->assertCount(1, $origen['items']);
        $this->assertSame('Plancha de Drywall', $origen['items'][0]['nombre']);
        $this->assertSame(3, $origen['items'][0]['cantidad']);
    }

    public function test_desde_ignora_un_origen_que_no_es_cotizacion(): void
    {
        $boleta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.factura.create', ['desde' => $boleta->id]));

        $this->assertNull($respuesta->viewData('origen'));
    }

    public function test_store_con_origen_deja_la_observacion_y_no_toca_la_cotizacion(): void
    {
        $cot = Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba', 'estado' => 'activa',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-01',
            'fecha_vencimiento' => '2026-09-01',
            'tipcomp' => '03',
            'n_seri' => 'B001',
            'n_comp' => '00000099',
            'razonsocial' => 'Cliente de Prueba',
            'monto' => 100,
            'tipo_operacion' => 'gravada',
            'precios_incluyen_igv' => 1,
            'origen_id' => $cot->id,
        ]);

        $respuesta->assertRedirect();

        $nueva = Venta::where('n_seri', 'B001')->where('n_comp', '00000099')->firstOrFail();
        $this->assertStringContainsString('CT01-00000001', $nueva->observaciones);

        $cot->refresh();
        $this->assertSame('activa', $cot->estado);
    }

    public function test_cotizacion_no_aparece_ni_suma_en_el_listado_general(): void
    {
        Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 500,
        ]);
        Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 100,
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index'));

        // La cotización no debe estar en ningún grupo del listado general.
        $tipos = collect($respuesta->viewData('grupos'))->flatten()->pluck('tipcomp');
        $this->assertNotContains('COT', $tipos);
        $this->assertContains('03', $tipos);

        // Los totales/KPIs tampoco deben incluir el monto de la cotización.
        $this->assertSame(1, $respuesta->viewData('nVentas'));
        $this->assertSame(100.0, $respuesta->viewData('totalGeneral'));
    }

    public function test_la_lista_propia_de_cotizaciones_si_las_sigue_mostrando(): void
    {
        Venta::create([
            'fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'estado' => 'activa', 'total' => 500,
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.index', ['tipcomp' => 'COT']));

        $this->assertSame(1, $respuesta->viewData('nVentas'));
        $this->assertSame(500.0, $respuesta->viewData('totalGeneral'));
    }
}
