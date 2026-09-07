<?php

namespace Tests\Feature;

use App\Models\Personal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el bug de producción: al registrar un colaborador con usuario de
 * acceso pero dejando el select "Rol de acceso" en su opción por defecto
 * ("Sin acceso", value=""), Laravel normaliza ese "" a null y rompía el
 * INSERT en usuarios.rol (NOT NULL) — ver PersonalController::sincronizarAcceso().
 */
class PersonalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosBase(): array
    {
        return [
            'dni' => '12345678',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'cargo' => 'Operario',
            'tipo_contrato' => 'Planilla',
        ];
    }

    public function test_crear_colaborador_con_usuario_de_acceso_pero_rol_vacio_no_revienta(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.personal.store'), $this->datosBase() + [
                'acceso_username' => 'jperez',
                'acceso_rol' => '',
                'acceso_password' => 'clave123',
            ])
            ->assertRedirect(route('admin.personal.index'));

        $this->assertDatabaseHas('usuarios', [
            'username' => 'jperez',
            'rol' => 'secretaria',
        ]);
    }

    public function test_crear_colaborador_sin_usuario_de_acceso_no_crea_usuario(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.personal.store'), $this->datosBase())
            ->assertRedirect(route('admin.personal.index'));

        $empleado = Personal::where('dni', '12345678')->first();
        $this->assertNull($empleado->usuario_id);
    }

    public function test_crear_colaborador_con_rol_explicito_lo_respeta(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.personal.store'), $this->datosBase() + [
                'acceso_username' => 'jcontador',
                'acceso_rol' => 'contador',
                'acceso_password' => 'clave123',
            ])
            ->assertRedirect(route('admin.personal.index'));

        $this->assertDatabaseHas('usuarios', [
            'username' => 'jcontador',
            'rol' => 'contador',
        ]);
    }
}
