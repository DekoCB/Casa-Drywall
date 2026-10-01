<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Descargar PDF" (Cotización/Nota de Venta/Boleta/Factura) — para mandarle
 * el documento al interesado sin depender de que ya esté aceptado por SUNAT,
 * a diferencia del "PDF oficial" (`pdf-sunat`, solo existe una vez aceptado).
 * Se genera con dompdf a partir de la misma vista que ya se ve/imprime en
 * pantalla (`comprobante`/`cotizacion`), con la barra de acciones oculta.
 */
class VentaDescargarPdfTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_descarga_pdf_de_una_cotizacion(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-10-01', 'tipcomp' => 'COT', 'n_seri' => 'CT01', 'n_comp' => '00000001',
            'estado' => 'activa', 'razonsocial' => 'Cliente de Prueba', 'total' => 100,
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.descargar-pdf', $venta));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_descarga_pdf_de_una_nota_de_venta(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-10-01', 'fecha_vencimiento' => '2026-10-01', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'estado' => 'activa',
            'razonsocial' => 'Cliente de Prueba', 'total' => 100, 'metodo_pago' => 'Efectivo',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.descargar-pdf', $venta));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_descarga_pdf_de_una_boleta(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-10-01', 'fecha_vencimiento' => '2026-10-01', 'tipcomp' => '03',
            'n_seri' => 'B001', 'n_comp' => '00000001', 'estado' => 'activa',
            'razonsocial' => 'Cliente de Prueba', 'total' => 118, 'baseimp' => 100, 'igv' => 18,
            'metodo_pago' => 'Efectivo', 'moneda' => 'PEN',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.descargar-pdf', $venta));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_descarga_pdf_de_una_factura(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-10-01', 'fecha_vencimiento' => '2026-10-01', 'tipcomp' => '01',
            'n_seri' => 'F001', 'n_comp' => '00000001', 'estado' => 'activa',
            'razonsocial' => 'Cliente de Prueba SAC', 'total' => 118, 'baseimp' => 100, 'igv' => 18,
            'metodo_pago' => 'Efectivo', 'moneda' => 'PEN',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.descargar-pdf', $venta));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
    }

    /** No requiere ningún estado SUNAT previo — a diferencia de "PDF oficial". */
    public function test_descarga_pdf_funciona_aunque_la_boleta_este_pendiente_de_envio(): void
    {
        $venta = Venta::create([
            'fecha' => '2026-10-01', 'fecha_vencimiento' => '2026-10-01', 'tipcomp' => '03',
            'n_seri' => 'B001', 'n_comp' => '00000002', 'estado' => 'activa',
            'razonsocial' => 'Cliente de Prueba', 'total' => 118, 'baseimp' => 100, 'igv' => 18,
            'metodo_pago' => 'Efectivo', 'moneda' => 'PEN', 'estado_factura' => 'pendiente',
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.descargar-pdf', $venta));

        $respuesta->assertOk();
    }
}
