<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Selector de empresa (multi-empresa): la parte de ruteo/sesión, que sí se
 * puede probar sin dos bases de datos reales. El cambio de conexión en sí
 * (`SeleccionarEmpresa`, cuando `conexion` no es null) se verificó a mano
 * contra MySQL real — la suite usa SQLite y no puede validar eso.
 */
class SeleccionarEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_sin_empresa_elegida_es_mandado_al_selector(): void
    {
        $this->get('/login')->assertRedirect(route('empresas.elegir'));
    }

    public function test_el_selector_muestra_las_empresas_configuradas(): void
    {
        $respuesta = $this->get(route('empresas.elegir'));

        $respuesta->assertOk();
        $respuesta->assertSee('Casa Drywall');
        $respuesta->assertSee('Jitk');
    }

    public function test_elegir_una_empresa_valida_guarda_la_sesion_y_manda_al_login(): void
    {
        $respuesta = $this->get(route('empresas.seleccionar', 'jitk'));

        $respuesta->assertRedirect(route('login'));
        $this->assertSame('jitk', session('empresa_activa'));
    }

    public function test_elegir_una_empresa_que_no_existe_da_404(): void
    {
        $this->get(route('empresas.seleccionar', 'no-existe'))->assertNotFound();
    }

    public function test_una_vez_elegida_la_empresa_el_login_ya_es_alcanzable(): void
    {
        $this->get(route('empresas.seleccionar', 'casadrywall'));

        $this->get(route('login'))->assertOk();
    }
}
