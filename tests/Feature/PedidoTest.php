<?php

namespace Tests\Feature;

use App\Models\PedidoCliente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedidoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_crear_un_pedido_sin_archivo(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.pedidos.store'), [
                'fecha' => '2026-09-03',
                'cliente_nombre' => 'Constructora ABC',
                'ruc' => '20123456789',
                'total_soles' => 450.50,
                'estado' => 'Pendiente',
            ])
            ->assertRedirect(route('admin.pedidos.index'));

        $this->assertDatabaseHas('pedidos_clientes', [
            'cliente_nombre' => 'Constructora ABC',
            'estado' => 'Pendiente',
        ]);
    }

    public function test_crear_un_pedido_con_archivo_adjunto(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'web')
            ->post(route('admin.pedidos.store'), [
                'fecha' => '2026-09-03',
                'cliente_nombre' => 'Cliente con archivo',
                'total_soles' => 100,
                'estado' => 'Pendiente',
                'archivo_pedido' => UploadedFile::fake()->create('pedido.pdf', 200),
            ])
            ->assertRedirect();

        $pedido = PedidoCliente::where('cliente_nombre', 'Cliente con archivo')->firstOrFail();
        $this->assertNotNull($pedido->archivo_pedido);
        Storage::disk('public')->assertExists($pedido->archivo_pedido);
    }

    public function test_editar_un_pedido(): void
    {
        $pedido = PedidoCliente::create([
            'fecha' => '2026-09-01', 'cliente_nombre' => 'Antes', 'total_soles' => 10, 'estado' => 'Pendiente',
        ]);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.pedidos.update', $pedido), [
                'fecha' => '2026-09-01',
                'cliente_nombre' => 'Después',
                'total_soles' => 20,
                'estado' => 'Entregado',
            ])
            ->assertRedirect();

        $pedido->refresh();
        $this->assertSame('Después', $pedido->cliente_nombre);
        $this->assertSame('Entregado', $pedido->estado);
    }

    public function test_eliminar_un_pedido_borra_tambien_su_archivo(): void
    {
        Storage::fake('public');
        $archivo = UploadedFile::fake()->create('pedido.pdf', 100)->store('pedidos', 'public');

        $pedido = PedidoCliente::create([
            'fecha' => '2026-09-01', 'cliente_nombre' => 'A eliminar', 'total_soles' => 10,
            'estado' => 'Pendiente', 'archivo_pedido' => $archivo,
        ]);

        $this->actingAs($this->admin(), 'web')
            ->delete(route('admin.pedidos.destroy', $pedido))
            ->assertRedirect();

        $this->assertDatabaseMissing('pedidos_clientes', ['id' => $pedido->id]);
        Storage::disk('public')->assertMissing($archivo);
    }

    public function test_pantalla_de_pedidos_renderiza_y_respeta_el_query_crear(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->get(route('admin.pedidos.index', ['crear' => 1]))
            ->assertOk()
            ->assertSee('Nuevo Pedido');
    }
}
