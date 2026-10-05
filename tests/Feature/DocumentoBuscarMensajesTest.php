<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Un 404 de SUNAT/RENIEC (el documento genuinamente no existe) y una falla
 * del servicio (429/caído) usaban el mismo mensaje "intenta de nuevo en
 * unos segundos" — para un 404 real eso es falso, y empujaba a la persona a
 * presionar Buscar varias veces seguidas. Como cada click dispara hasta 3
 * peticiones reales (reintentos de `ConsultaDocumentoService`), esos clicks
 * de más eran justo lo que terminaba agotando el límite real del servicio
 * (confirmado en el log de producción del 5 de octubre: un 404 seguido de
 * una ráfaga de 429 segundos después, mismo documento).
 */
class DocumentoBuscarMensajesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_ruc_no_encontrado_da_un_mensaje_claro_sin_invitar_a_reintentar(): void
    {
        Http::fake(['api.apis.net.pe/*' => Http::response('No encontrado', 404)]);

        $this->actingAs($this->admin(), 'web')
            ->getJson(route('admin.documentos.buscar', ['ruc', '20601111199']))
            ->assertStatus(404)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'No se encontró ese RUC en SUNAT. Verifica el número o completa los datos a mano.');
    }

    public function test_ruc_con_servicio_caido_avisa_que_reintentar_seguido_no_ayuda(): void
    {
        Http::fake(['api.apis.net.pe/*' => Http::response('Too Many Requests', 429)]);

        $this->actingAs($this->admin(), 'web')
            ->getJson(route('admin.documentos.buscar', ['ruc', '20601111199']))
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'El servicio de SUNAT no respondió (demasiadas consultas). Espera unos segundos e intenta de nuevo — reintentar varias veces seguidas no ayuda.');
    }

    public function test_dni_no_encontrado_da_un_mensaje_claro_sin_invitar_a_reintentar(): void
    {
        Http::fake(['api.apis.net.pe/*' => Http::response('No encontrado', 404)]);

        $this->actingAs($this->admin(), 'web')
            ->getJson(route('admin.documentos.buscar', ['dni', '70332245']))
            ->assertStatus(404)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'No se encontró ese DNI en RENIEC. Verifica el número o escribe el nombre a mano.');
    }

    public function test_dni_con_servicio_caido_avisa_que_reintentar_seguido_no_ayuda(): void
    {
        Http::fake(['api.apis.net.pe/*' => Http::response('Too Many Requests', 429)]);

        $this->actingAs($this->admin(), 'web')
            ->getJson(route('admin.documentos.buscar', ['dni', '70332245']))
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'RENIEC no respondió (demasiadas consultas). Espera unos segundos e intenta de nuevo — reintentar varias veces seguidas no ayuda.');
    }
}
