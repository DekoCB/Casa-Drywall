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
}
