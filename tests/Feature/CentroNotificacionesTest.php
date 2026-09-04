<?php

namespace Tests\Feature;

use App\Models\Cobranza;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\CentroNotificaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campanita de notificaciones: cada categoría se calcula en vivo (sin tabla
 * de eventos) y el estado "leído" se guarda por usuario en
 * `notificacion_lecturas`.
 */
class CentroNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_producto_agotado_aparece_en_inventario(): void
    {
        Producto::create(['codigo' => 'P001', 'nombre' => 'Plancha Drywall', 'stock' => 0, 'precio_venta' => 10]);
        Producto::create(['codigo' => 'P002', 'nombre' => 'Perfil Metálico', 'stock' => 50, 'precio_venta' => 20]);

        $centro = app(CentroNotificaciones::class)->paraUsuario($this->admin());

        $detalles = collect($centro['porCategoria']['inventario'])->pluck('detalle');
        $this->assertContains('Plancha Drywall', $detalles);
        $this->assertNotContains('Perfil Metálico', $detalles);
    }

    public function test_cobranza_vencida_90_dias_aparece_en_pagos_y_una_reciente_no(): void
    {
        Cobranza::create([
            'tipo' => 'FT', 'numero' => 'F001-001', 'cliente_nombre' => 'Cliente Vencido',
            'fecha_emision' => now()->subDays(120)->toDateString(),
            'fecha_vencimiento' => now()->subDays(95)->toDateString(),
            'monto_total' => 100, 'monto_pagado' => 0, 'monto_pendiente' => 100, 'estado' => 'pendiente',
        ]);
        Cobranza::create([
            'tipo' => 'FT', 'numero' => 'F001-002', 'cliente_nombre' => 'Cliente al día',
            'fecha_emision' => now()->subDays(10)->toDateString(),
            'fecha_vencimiento' => now()->addDays(20)->toDateString(),
            'monto_total' => 100, 'monto_pagado' => 0, 'monto_pendiente' => 100, 'estado' => 'pendiente',
        ]);

        $centro = app(CentroNotificaciones::class)->paraUsuario($this->admin());

        $detalles = collect($centro['porCategoria']['pagos'])->pluck('detalle')->implode(' | ');
        $this->assertStringContainsString('Cliente Vencido', $detalles);
        $this->assertStringNotContainsString('Cliente al día', $detalles);
    }

    public function test_comprobante_pendiente_de_sunat_aparece_en_comprobantes(): void
    {
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000001',
            'estado_factura' => 'pendiente', 'estado' => 'activa',
        ]);
        Venta::create([
            'fecha' => now()->toDateString(), 'tipcomp' => '03', 'n_seri' => 'B001', 'n_comp' => '00000002',
            'estado_factura' => 'aceptado', 'estado' => 'activa',
        ]);

        $centro = app(CentroNotificaciones::class)->paraUsuario($this->admin());

        $detalles = collect($centro['porCategoria']['comprobantes'])->pluck('detalle')->implode(' | ');
        $this->assertStringContainsString('B001-00000001', $detalles);
        $this->assertStringNotContainsString('B001-00000002', $detalles);
    }

    public function test_marcar_todas_leidas_persiste_por_usuario(): void
    {
        Producto::create(['codigo' => 'P001', 'nombre' => 'Plancha Drywall', 'stock' => 0, 'precio_venta' => 10]);
        $usuario = $this->admin();

        $antes = app(CentroNotificaciones::class)->paraUsuario($usuario);
        $this->assertGreaterThan(0, $antes['noLeidas']);

        $this->actingAs($usuario, 'web')
            ->post(route('admin.notificaciones.marcar-leidas'))
            ->assertRedirect();

        $this->assertDatabaseHas('notificacion_lecturas', [
            'usuario_id' => $usuario->id,
            'clave' => 'inventario:1',
        ]);

        $despues = app(CentroNotificaciones::class)->paraUsuario($usuario);
        $this->assertSame(0, $despues['noLeidas']);

        // Otro usuario no se ve afectado por lo que el primero marcó.
        $otro = app(CentroNotificaciones::class)->paraUsuario($this->admin());
        $this->assertGreaterThan(0, $otro['noLeidas']);
    }

    public function test_topbar_incluye_la_campanita_en_una_pagina_admin(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('notifTrigger', false);
    }
}
