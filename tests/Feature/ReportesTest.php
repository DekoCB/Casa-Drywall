<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Centro de Reportes: hub por áreas y los 3 reportes con datos reales
 * (Análisis ABC, Rotación de Inventario, Aging de Cuentas por Cobrar).
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function ventaConItems(string $fecha, string $tipcomp, array $items): Venta
    {
        static $n = 0;
        $n++;

        $venta = Venta::create([
            'fecha' => $fecha, 'tipcomp' => $tipcomp, 'n_seri' => 'X001',
            'n_comp' => str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'estado' => 'activa',
        ]);

        foreach ($items as $item) {
            VentaDetalle::create([
                'venta_id' => $venta->id, 'prod_codigo' => $item['codigo'], 'prod_nombre' => $item['nombre'],
                'cantidad' => $item['cantidad'], 'precio_unitario' => $item['precio'],
                'subtotal' => $item['cantidad'] * $item['precio'],
            ]);
        }

        return $venta;
    }

    public function test_hub_lista_tarjetas_agrupadas_por_area(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.reportes.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Análisis ABC');
        $respuesta->assertSee('Rotación de Inventario');
        $respuesta->assertSee('Cuentas por Cobrar');
    }

    public function test_abc_clasifica_productos_y_excluye_cotizaciones(): void
    {
        // Un producto que domina el ingreso (debe ser A) y otro marginal (C).
        $this->ventaConItems('2026-06-01', '03', [
            ['codigo' => 'P001', 'nombre' => 'Placa Drywall', 'cantidad' => 100, 'precio' => 50],
        ]);
        $this->ventaConItems('2026-06-02', '03', [
            ['codigo' => 'P002', 'nombre' => 'Tornillo', 'cantidad' => 10, 'precio' => 0.5],
        ]);
        // Una Cotización con el mismo producto no debe sumarse al ranking.
        $this->ventaConItems('2026-06-03', 'COT', [
            ['codigo' => 'P001', 'nombre' => 'Placa Drywall', 'cantidad' => 500, 'precio' => 50],
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->get(route('admin.reportes.abc', ['desde' => '2026-06-01', 'hasta' => '2026-06-30']));

        $respuesta->assertOk();
        $items = $respuesta->viewData('items');

        $placa = $items->firstWhere('codigo', 'P001');
        $this->assertSame(100, $placa['cantidad']); // no 600 — la cotización no cuenta.
        $this->assertSame('A', $placa['clase']);
    }

    public function test_rotacion_marca_baja_cuando_no_hubo_ventas_en_el_periodo(): void
    {
        Producto::create(['codigo' => 'P010', 'nombre' => 'Producto sin ventas', 'stock' => 20, 'precio_venta' => 10]);
        $vendido = Producto::create(['codigo' => 'P011', 'nombre' => 'Producto con ventas', 'stock' => 20, 'precio_venta' => 10]);

        $venta = $this->ventaConItems(now()->toDateString(), '03', [
            ['codigo' => 'P011', 'nombre' => 'Producto con ventas', 'cantidad' => 30, 'precio' => 10],
        ]);
        VentaDetalle::where('venta_id', $venta->id)->update(['producto_id' => $vendido->id]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.reportes.rotacion'));

        $respuesta->assertOk();
        $items = $respuesta->viewData('items');

        $this->assertSame('Baja', $items->firstWhere('codigo', 'P010')['estado']);
        $this->assertSame('Alta', $items->firstWhere('codigo', 'P011')['estado']);
    }

    public function test_aging_agrupa_por_cliente_en_tramos_de_30_dias(): void
    {
        $cliente = Cliente::create(['nombres' => 'Constructora ABC', 'numero_documento' => '20123456789']);

        Cobranza::create([
            'tipo' => 'FT', 'numero' => 'F001-001', 'cliente_id' => $cliente->id, 'cliente_nombre' => 'Constructora ABC',
            'fecha_emision' => now()->subDays(120)->toDateString(), 'fecha_vencimiento' => now()->subDays(95)->toDateString(),
            'monto_total' => 1000, 'monto_pagado' => 0, 'monto_pendiente' => 1000, 'estado' => 'pendiente',
        ]);
        Cobranza::create([
            'tipo' => 'FT', 'numero' => 'F001-002', 'cliente_id' => $cliente->id, 'cliente_nombre' => 'Constructora ABC',
            'fecha_emision' => now()->subDays(10)->toDateString(), 'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'monto_total' => 200, 'monto_pagado' => 0, 'monto_pendiente' => 200, 'estado' => 'pendiente',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.reportes.aging'));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('items')->firstWhere('cliente', 'Constructora ABC');

        $this->assertSame(2, $fila['docs']);
        $this->assertSame(1000.0, $fila['d90_mas']);
        $this->assertSame(200.0, $fila['d1_30']);
        $this->assertSame(1200.0, $fila['total']);
    }

    public function test_exportaciones_excel_y_pdf_responden_con_el_content_type_correcto(): void
    {
        $admin = $this->actingAs($this->admin(), 'web');

        $excel = $admin->get(route('admin.reportes.abc.excel'));
        $excel->assertOk();
        $excel->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $pdf = $admin->get(route('admin.reportes.rotacion.pdf'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));

        $agingExcel = $admin->get(route('admin.reportes.aging.excel'));
        $agingExcel->assertOk();
    }
}
