<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRememberSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_con_recuerdame_marcado_setea_remember_token(): void
    {
        $usuario = Usuario::create(['username' => 'smoketest', 'password' => 'secreto123', 'rol' => 'admin']);
        $this->assertNull($usuario->remember_token);

        $respuesta = $this->withSession(['empresa_activa' => 'casa_drywall'])->post(route('login'), [
            'username' => 'smoketest',
            'password' => 'secreto123',
            'remember' => '1',
        ]);

        $respuesta->assertRedirect();
        $this->assertAuthenticatedAs($usuario->fresh());
        $this->assertNotNull($usuario->fresh()->remember_token, 'remember_token debería quedar seteado cuando se marca Recuérdame');

        $cookieNames = collect($respuesta->headers->getCookies())->map->getName();
        $this->assertTrue($cookieNames->contains(fn ($n) => str_starts_with($n, 'remember_web_')), 'Debe setear la cookie remember_web_* de larga duración');
    }

    public function test_login_sin_recuerdame_no_setea_remember_token(): void
    {
        $usuario = Usuario::create(['username' => 'smoketest2', 'password' => 'secreto123', 'rol' => 'admin']);

        $respuesta = $this->withSession(['empresa_activa' => 'casa_drywall'])->post(route('login'), [
            'username' => 'smoketest2',
            'password' => 'secreto123',
        ]);

        $respuesta->assertRedirect();
        $this->assertAuthenticatedAs($usuario->fresh());
        $this->assertNull($usuario->fresh()->remember_token);
    }
}
