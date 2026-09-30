<?php

namespace Tests\Feature;

use App\Models\MetodoPago;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nota de Venta, Boleta y Factura piden al menos un medio de pago (Efectivo,
 * Yape, Plin, Transferencia bancaria, etc. — el mismo catálogo real de
 * `metodos_pago`) — y admiten más de uno repartido en la misma venta (ej.
 * parte Yape + parte Efectivo), mismo patrón que ya usaba el POS
 * (`VentaPago`, una fila por medio). `Venta.metodo_pago` queda como un
 * resumen: el nombre del único medio, o "Mixto" si hubo más de uno.
 * Cotización queda exenta: todavía no hay un pago real que registrar, es
 * solo un presupuesto.
 */
class VentaMetodoPagoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    private function datosNota(array $sobrescribe = []): array
    {
        return array_merge([
            'fecha' => '2026-09-16',
            'fecha_vencimiento' => '2026-09-16',
            'tipcomp' => 'NV',
            'n_seri' => 'NV01',
            'n_comp' => '00000001',
            'razonsocial' => 'Cliente de Prueba',
            'monto' => 100,
            'tipo_operacion' => 'gravada',
            'precios_incluyen_igv' => 1,
        ], $sobrescribe);
    }

    public function test_nota_de_venta_sin_pagos_es_rechazada(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota());

        $respuesta->assertSessionHasErrors('pagos');
        $this->assertSame(0, Venta::count());
    }

    public function test_boleta_sin_pagos_es_rechazada(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'tipcomp' => '03', 'n_seri' => 'B001',
        ]));

        $respuesta->assertSessionHasErrors('pagos');
        $this->assertSame(0, Venta::count());
    }

    public function test_nota_de_venta_con_un_pago_se_guarda(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [['metodo_pago' => 'Yape', 'monto' => 100]],
        ]));

        $respuesta->assertRedirect();

        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();
        $this->assertSame('Yape', $venta->metodo_pago);

        $pago = VentaPago::where('venta_id', $venta->id)->firstOrFail();
        $this->assertSame('Yape', $pago->metodo_pago);
        $this->assertEquals(100, $pago->monto);
    }

    /** El caso real que reportó el negocio: una parte por Yape, la otra en Efectivo. */
    public function test_nota_de_venta_con_pago_mixto_guarda_las_dos_filas_y_resume_como_mixto(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [
                ['metodo_pago' => 'Yape', 'monto' => 60, 'referencia' => 'op-123'],
                ['metodo_pago' => 'Efectivo', 'monto' => 40],
            ],
        ]));

        $respuesta->assertRedirect();

        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();
        $this->assertSame('Mixto', $venta->metodo_pago);

        $pagos = VentaPago::where('venta_id', $venta->id)->orderBy('id')->get();
        $this->assertCount(2, $pagos);
        $this->assertSame('Yape', $pagos[0]->metodo_pago);
        $this->assertEquals(60, $pagos[0]->monto);
        $this->assertSame('op-123', $pagos[0]->referencia);
        $this->assertSame('Efectivo', $pagos[1]->metodo_pago);
        $this->assertEquals(40, $pagos[1]->monto);
    }

    /** Una fila con medio elegido pero sin monto (o en 0) no cuenta — mismo criterio que el POS. */
    public function test_una_fila_de_pago_sin_monto_se_descarta(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [
                ['metodo_pago' => 'Yape', 'monto' => 100],
                ['metodo_pago' => 'Efectivo', 'monto' => 0],
            ],
        ]));

        $respuesta->assertRedirect();

        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();
        $this->assertSame('Yape', $venta->metodo_pago); // no "Mixto": la segunda fila no contó
        $this->assertCount(1, VentaPago::where('venta_id', $venta->id)->get());
    }

    public function test_cotizacion_no_exige_pagos(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'tipcomp' => 'COT', 'n_seri' => 'CT01',
        ]));

        $respuesta->assertRedirect();
        $respuesta->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Venta::count());
    }

    /**
     * El selector de medio de pago de cada fila no va alfabético — el
     * negocio pidió un orden fijo: Efectivo, Yape, Plin, Transferencia
     * bancaria, Tarjeta, Depósito bancario (los últimos dos, sin posición
     * pedida, van al final). Se lee directo del array que arma el JS
     * (`METODOS_PAGO`), la única fuente de las opciones de cada fila.
     */
    public function test_el_orden_de_los_medios_de_pago_respeta_el_orden_fijo_pedido(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.create'));

        $respuesta->assertOk();

        $html = $respuesta->getContent();
        $inicio = strpos($html, 'const METODOS_PAGO');
        $fin = strpos($html, ';', $inicio);
        $bloque = substr($html, $inicio, $fin - $inicio);

        // @json() escapa los caracteres no-ASCII (ej. "ó" → "ó"): se busca
        // cada nombre tal como realmente queda en el JSON, no el texto plano
        // (las comillas se descartan: @json también las escapa a ").
        $posiciones = [];
        foreach (['Efectivo', 'Yape', 'Plin', 'Transferencia bancaria', 'Tarjeta', 'Depósito bancario'] as $nombre) {
            $posiciones[$nombre] = strpos($bloque, trim(json_encode($nombre), '"'));
        }

        $this->assertSame(
            ['Efectivo', 'Yape', 'Plin', 'Transferencia bancaria', 'Tarjeta', 'Depósito bancario'],
            collect($posiciones)->sort()->keys()->all()
        );
    }

    public function test_pagina_de_edicion_muestra_el_catalogo_de_metodos_de_pago(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 100]],
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.edit', $venta));

        $respuesta->assertOk();
        $respuesta->assertSee('Forma de pago', false);
        $respuesta->assertSee('Yape', false);
        $respuesta->assertSee('Plin', false);
    }

    /** Al abrir la edición, cada pago ya guardado precarga su propia fila. */
    public function test_pagina_de_edicion_precarga_los_pagos_ya_guardados(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [
                ['metodo_pago' => 'Yape', 'monto' => 60],
                ['metodo_pago' => 'Efectivo', 'monto' => 40],
            ],
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ventas.factura.edit', $venta));

        $respuesta->assertOk();
        // @json() escapa comillas como " (seguro para incrustar en <script>).
        $html = $respuesta->getContent();
        $this->assertStringContainsString('metodo_pago":"Yape', $html);
        $this->assertStringContainsString('metodo_pago":"Efectivo', $html);
    }

    public function test_editar_sin_pagos_es_rechazado(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 100]],
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ]);

        $respuesta->assertSessionHasErrors('pagos');
        $this->assertSame('Efectivo', $venta->fresh()->metodo_pago);
    }

    public function test_editar_puede_cambiar_el_medio_de_pago(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 100]],
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [['metodo_pago' => 'Transferencia bancaria', 'monto' => 100]],
        ]);

        $respuesta->assertRedirect();
        $this->assertSame('Transferencia bancaria', $venta->fresh()->metodo_pago);
    }

    /** Editar reemplaza el desglose completo, no lo acumula sobre el anterior. */
    public function test_editar_reemplaza_el_desglose_de_pagos_por_el_nuevo(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 100]],
        ]));
        $venta = Venta::where('n_seri', 'NV01')->firstOrFail();

        $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'NV',
            'n_seri' => 'NV01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
            'pagos' => [
                ['metodo_pago' => 'Yape', 'monto' => 60],
                ['metodo_pago' => 'Plin', 'monto' => 40],
            ],
        ]);

        $venta->refresh();
        $this->assertSame('Mixto', $venta->metodo_pago);
        $pagos = VentaPago::where('venta_id', $venta->id)->orderBy('id')->get();
        $this->assertCount(2, $pagos);
        $this->assertSame(['Yape', 'Plin'], $pagos->pluck('metodo_pago')->all());
    }

    /** Uno nuevo, agregado desde Configuración y no contemplado en el orden fijo, cae al final sin romper nada. */
    public function test_metodo_pago_no_contemplado_en_el_orden_cae_al_final(): void
    {
        MetodoPago::create(['nombre' => 'QR Interoperable', 'activo' => true]);

        $orden = MetodoPago::activosOrdenados()->pluck('nombre')->all();

        $this->assertSame('QR Interoperable', end($orden));
    }

    public function test_editar_cotizacion_no_exige_pagos(): void
    {
        $this->actingAs($this->admin(), 'web')->post(route('admin.ventas.factura.store'), $this->datosNota([
            'tipcomp' => 'COT', 'n_seri' => 'CT01',
        ]));
        $venta = Venta::where('n_seri', 'CT01')->firstOrFail();

        $respuesta = $this->actingAs($this->admin(), 'web')->put(route('admin.ventas.factura.update', $venta), [
            'fecha' => '2026-09-16', 'fecha_vencimiento' => '2026-09-16', 'tipcomp' => 'COT',
            'n_seri' => 'CT01', 'n_comp' => '00000001', 'razonsocial' => 'Cliente de Prueba',
            'monto' => 100, 'tipo_operacion' => 'gravada', 'precios_incluyen_igv' => 1,
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionDoesntHaveErrors();
    }
}
