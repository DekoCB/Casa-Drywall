<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los tres accesos nuevos del submenú de Ventas: filtro "No enviados",
 * filtro "Anulaciones" y la acción de anular (acotada a lo que nunca
 * llegó a comprometerse con SUNAT).
 */
class VentasSubmenuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_filtro_no_enviados_solo_muestra_boleta_factura_sin_estado_factura(): void
    {
        // 'pendiente' es el valor por defecto de la columna (nunca se tocó).
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado_factura' => 'pendiente']);
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '01', 'n_seri' => 'F001', 'n_comp' => '00000002', 'estado_factura' => 'aceptado']);
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000003', 'estado_factura' => 'pendiente']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.index', ['estado_factura' => 'no_enviado']));

        $respuesta->assertOk();
        $ids = collect($respuesta->viewData('grupos'))->flatten()->pluck('n_comp');
        $this->assertContains('00000001', $ids);
        $this->assertNotContains('00000002', $ids);
        $this->assertNotContains('00000003', $ids);
    }

    public function test_filtro_anulaciones_muestra_lo_que_el_listado_normal_excluye(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000001', 'estado' => 'cancelada']);
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000002', 'estado' => 'activa']);

        $normal = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index'));
        $anuladas = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index', ['estado' => 'cancelada']));

        $idsNormal = collect($normal->viewData('grupos'))->flatten()->pluck('n_comp');
        $idsAnuladas = collect($anuladas->viewData('grupos'))->flatten()->pluck('n_comp');

        $this->assertNotContains('00000001', $idsNormal);
        $this->assertContains('00000002', $idsNormal);

        $this->assertContains('00000001', $idsAnuladas);
        $this->assertNotContains('00000002', $idsAnuladas);
    }

    public function test_listado_de_cotizaciones_no_muestra_montos_pero_si_puede_eliminarlas(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001', 'estado' => 'activa', 'total' => 999.99]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.index', ['tipcomp' => 'COT']));

        $respuesta->assertOk();
        // Una cotización es un presupuesto, no una venta comprometida: no
        // corresponde mostrar un total de facturación por ella.
        $respuesta->assertDontSee('Total General');
        $respuesta->assertDontSee('gh-total');
        // Pero, al no tener ningún efecto ante SUNAT, sí se puede borrar
        // directo (no le corresponde "Anular", que es para lo que ya salió
        // a producción).
        $respuesta->assertSee('btn-del-v');
    }

    public function test_listado_normal_si_muestra_montos_y_boton_eliminar(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'total' => 100]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Total General');
        $respuesta->assertSee('btn-del-v');
    }

    public function test_anular_rechaza_una_cotizacion(): void
    {
        // Una Cotización no tiene efecto ante SUNAT ni genera cobranza: se
        // borra directo con "Eliminar", no le corresponde "Anular".
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001', 'estado' => 'activa']);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.ventas.anular', $venta))
            ->assertRedirect();

        $this->assertSame('activa', $venta->fresh()->estado);
    }

    public function test_anular_funciona_sobre_boleta_no_enviada(): void
    {
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'estado_factura' => 'pendiente']);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.ventas.anular', $venta))
            ->assertRedirect();

        $this->assertSame('cancelada', $venta->fresh()->estado);
    }

    public function test_anular_rechaza_una_boleta_ya_enviada_a_sunat(): void
    {
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'estado_factura' => 'aceptado']);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.ventas.anular', $venta))
            ->assertRedirect();

        // No cambia: sigue activa, tiene que corregirse con Nota de Crédito.
        $this->assertSame('activa', $venta->fresh()->estado);
    }

    /**
     * `destroy()` era un DELETE real — sin ningún rastro y sin forma de
     * revertirlo, ya causó un incidente real (cobranza huérfana de una
     * Cotización borrada). Ahora es baja lógica, igual que anular().
     */
    public function test_eliminar_una_cotizacion_es_baja_logica_no_borrado_real(): void
    {
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001', 'estado' => 'activa']);

        $this->actingAs($this->admin(), 'web')
            ->delete(route('admin.ventas.destroy', $venta))
            ->assertRedirect();

        $this->assertSame('eliminada', $venta->fresh()->estado);
        $this->assertSame(1, Venta::where('id', $venta->id)->count());
    }

    public function test_eliminar_rechaza_una_boleta_ya_enviada_a_sunat(): void
    {
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'estado_factura' => 'aceptado']);

        $this->actingAs($this->admin(), 'web')
            ->delete(route('admin.ventas.destroy', $venta))
            ->assertRedirect();

        $this->assertSame('activa', $venta->fresh()->estado);
    }

    public function test_eliminar_dos_veces_es_rechazado(): void
    {
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001', 'estado' => 'activa']);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->delete(route('admin.ventas.destroy', $venta))->assertRedirect();
        $segunda = $this->actingAs($admin, 'web')->delete(route('admin.ventas.destroy', $venta));

        $segunda->assertRedirect();
        $this->assertStringContainsString('ya fue anulado o eliminado', session('error'));
    }

    public function test_anular_rechaza_si_ya_esta_eliminada(): void
    {
        $venta = Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000001', 'estado' => 'eliminada']);

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.ventas.anular', $venta))
            ->assertRedirect();

        $this->assertSame('eliminada', $venta->fresh()->estado);
    }

    /** "Anulaciones" pasa a ser una lista combinada: lo anulado Y lo eliminado, no solo lo anulado. */
    public function test_filtro_anulaciones_incluye_tambien_lo_eliminado(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000001', 'estado' => 'cancelada']);
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000002', 'estado' => 'eliminada']);
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000003', 'estado' => 'activa']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.index', ['estado' => 'cancelada']));

        $ids = collect($respuesta->viewData('grupos'))->flatten()->pluck('n_comp');
        $this->assertContains('00000001', $ids);
        $this->assertContains('00000002', $ids);
        $this->assertNotContains('00000003', $ids);
    }

    public function test_no_muestra_anular_ni_eliminar_para_boleta_ya_enviada_a_sunat(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa', 'estado_factura' => 'aceptado']);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index'));

        $respuesta->assertDontSee('title="Anular"', false);
        $respuesta->assertDontSee('class="form-eliminar"', false);
    }

    public function test_no_muestra_anular_ni_eliminar_para_venta_ya_anulada(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'NV', 'n_seri' => 'NV01', 'n_comp' => '00000001', 'estado' => 'cancelada']);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.ventas.index', ['estado' => 'cancelada']));

        $respuesta->assertDontSee('title="Anular"', false);
        $respuesta->assertDontSee('class="form-eliminar"', false);
    }

    public function test_boton_anulaciones_aparece_en_la_cabecera(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index'));

        $respuesta->assertSee('Anulaciones');
        $respuesta->assertSee(route('admin.ventas.index', ['estado' => 'cancelada']), false);
    }

    public function test_listado_de_cotizaciones_muestra_convertida_en_cuando_hay_venta_generada(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-01', 'fecha_vencimiento' => '2026-09-01', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ]);
        $cot = Venta::where('n_seri', 'CT01')->firstOrFail();

        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-01', 'fecha_vencimiento' => '2026-09-01', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'metodo_pago' => 'Efectivo', 'origen_id' => $cot->id,
        ]);

        $respuesta = $this->actingAs($admin, 'web')->get(route('admin.ventas.index', ['tipcomp' => 'COT']));

        $respuesta->assertOk();
        $respuesta->assertSee('Convertida en');
        $respuesta->assertSee('NV NV01-00000001', false);
    }

    public function test_listado_de_cotizaciones_muestra_sin_convertir_cuando_no_hay_venta_generada(): void
    {
        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001', 'estado' => 'activa']);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index', ['tipcomp' => 'COT']));

        $respuesta->assertSee('Sin convertir');
    }

    public function test_filtro_convertida_filtra_las_cotizaciones(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-01', 'fecha_vencimiento' => '2026-09-01', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'Con venta',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ]);
        $cotConVenta = Venta::where('n_seri', 'CT01')->where('n_comp', '00000001')->firstOrFail();
        $this->actingAs($admin, 'web')->post(route('admin.ventas.factura.store'), [
            'fecha' => '2026-09-01', 'fecha_vencimiento' => '2026-09-01', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Con venta',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'metodo_pago' => 'Efectivo', 'origen_id' => $cotConVenta->id,
        ]);

        Venta::create(['fecha' => '2026-09-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000002', 'estado' => 'activa']);

        $convertidas = $this->actingAs($admin, 'web')
            ->get(route('admin.ventas.index', ['tipcomp' => 'COT', 'convertida' => 'si']));
        $sinConvertir = $this->actingAs($admin, 'web')
            ->get(route('admin.ventas.index', ['tipcomp' => 'COT', 'convertida' => 'no']));

        $this->assertSame(['00000001'], collect($convertidas->viewData('grupos'))->flatten()->pluck('n_comp')->all());
        $this->assertSame(['00000002'], collect($sinConvertir->viewData('grupos'))->flatten()->pluck('n_comp')->all());
    }
}
