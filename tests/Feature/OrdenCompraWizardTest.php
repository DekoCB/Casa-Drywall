<?php

namespace Tests\Feature;

use App\Models\OrdenCompra;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El alta de Orden de Compra pasó de 3 pasos a 2: "Para quién" (cliente y
 * documentos) ya no es su propia parada — el negocio compra siempre para
 * stock propio, nunca para un cliente puntual — así que lo que sobrevivió
 * de su contenido (número, fecha) se fusionó dentro de lo que era el paso
 * 3 ("Confirmar"), que ahora es el paso 2.
 *
 * Además, el negocio pidió simplificar más a fondo: ni "Para cliente" ni
 * los datos de despacho (factura, guía, referencia, aprobado por,
 * transporte, peso, bultos, fecha de vencimiento) son necesarios — la
 * orden solo pide lo mínimo (proveedor, productos, número/fecha, costo y
 * condición de pago). El Merch para clientes tampoco: es un catálogo
 * aparte que el negocio decidió que no hace falta comprar desde acá.
 */
class OrdenCompraWizardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        return Usuario::create(['username' => 'admin_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
    }

    public function test_pagina_de_alta_tiene_solo_dos_pasos(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertOk();
        $respuesta->assertSee('data-ir="1"', false);
        $respuesta->assertSee('data-ir="2"', false);
        $respuesta->assertDontSee('data-ir="3"', false);
        $respuesta->assertSee('data-paso="1"', false);
        $respuesta->assertSee('data-paso="2"', false);
        $respuesta->assertDontSee('data-paso="3"', false);
        $respuesta->assertDontSee('Para quién');

        // El contenido de lo que era el paso 2 (cabecera del documento) y el
        // paso 3 (costos) ahora conviven dentro del mismo paso 2.
        $respuesta->assertSee('Datos de la orden');
        $respuesta->assertSee('N° Orden');
        $respuesta->assertSee('Costos');
        $respuesta->assertSee('Condición de Pago', false);
    }

    public function test_el_asistente_de_javascript_avanza_solo_dos_tramos(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertSee('const TRAMOS = 2;', false);
    }

    public function test_ya_no_pide_cliente_ni_datos_de_despacho_ni_merch(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertOk();
        // "Factura"/"Bultos" solos son de más: "Facturas" ya aparece en el
        // menú lateral (Ventas) sin relación con este formulario — se
        // busca el id del campo puntual, que sí es exclusivo de la orden.
        $respuesta->assertDontSee('id="oc-cliente"', false);
        $respuesta->assertDontSee('PARA CLIENTE');
        $respuesta->assertDontSee('id="oc-factura"', false);
        $respuesta->assertDontSee('N° Guía de Remisión');
        $respuesta->assertDontSee('Referencia / O. Venta');
        $respuesta->assertDontSee('id="oc-aprobado-por"', false);
        $respuesta->assertDontSee('Empresa de Transporte');
        $respuesta->assertDontSee('Peso Total');
        $respuesta->assertDontSee('id="oc-bultos"', false);
        $respuesta->assertDontSee('Fecha de vencimiento');
        $respuesta->assertDontSee('Merch para clientes');
        $respuesta->assertDontSee('Peso/und kg');
    }

    /**
     * Una Orden de Compra es puro costo de compra — el precio de venta es
     * cosa de Ventas (Cotización/Nota de Venta/Boleta/Factura), nunca al
     * revés. El total ya tampoco se puede pisar a mano: siempre es la
     * suma real de las líneas agregadas.
     */
    public function test_no_pide_precio_de_venta_y_el_total_no_se_puede_editar(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertOk();
        $respuesta->assertDontSee('id="oc-pventa"', false);
        $respuesta->assertDontSee('Precio Venta Unitario');
        $respuesta->assertSee('id="oc-total-editable" step="0.01" min="0" value="0" readonly', false);
    }

    /** Los badges de sección quedaron con un hueco (4/5) al quitar Merch (que era el 3, opcional). */
    public function test_las_secciones_quedan_numeradas_sin_huecos(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->get(route('admin.ordenes-compra.create'));

        $respuesta->assertSee('<span class="ocd-num">1</span>', false);
        $respuesta->assertSee('<span class="ocd-num">2</span>', false);
        $respuesta->assertSee('<span class="ocd-num">3</span>', false);
        $respuesta->assertSee('<span class="ocd-num">4</span>', false);
        $respuesta->assertSee('<span class="ocd-num opcional">5</span>', false);
        $respuesta->assertDontSee('<span class="ocd-num">5</span>', false);
        $respuesta->assertDontSee('<span class="ocd-num">6</span>', false);
    }

    public function test_la_busqueda_de_productos_trae_precio_de_compra(): void
    {
        \App\Models\Producto::create([
            'codigo' => 'DRY-900', 'nombre' => 'Placa de prueba', 'estado' => 'activo',
            'precio_compra' => 12.5, 'precio_venta' => 20, 'stock' => 0, 'stock_minimo' => 0,
        ]);

        $respuesta = $this->actingAs($this->admin(), 'web')
            ->getJson('admin/productos/buscar?q=Placa+de+prueba');

        $respuesta->assertOk();
        $this->assertSame(12.5, (float) $respuesta->json()[0]['precio_compra']);
        $this->assertSame(20.0, (float) $respuesta->json()[0]['precio_venta']);
    }

    public function test_guardar_una_orden_no_exige_ninguno_de_los_campos_quitados(): void
    {
        $respuesta = $this->actingAs($this->admin(), 'web')->post(route('admin.ordenes-compra.store'), [
            'estado' => 'Pendiente',
            'gasto_unit' => '0',
            'condicion_pago' => 'contado',
            'tc' => '1',
            'proveedor' => 'Distribuidora Drywall SAC',
            'ruc' => '20123456789',
            'ref_fecha' => now()->format('Ymd'),
            'numero_orden' => 'OC-TEST-001',
            'fecha' => now()->toDateString(),
            'precio_venta' => '0',
            'total_usd' => '19.00',
            'total_soles' => '19.00',
            'productos' => json_encode([
                ['codigo' => '00001', 'descripcion' => 'PARANTE GALV. 89 X 38 X 0.45 X 3M', 'unidad' => 'Und.', 'precio_unit_usd' => 9.5, 'cantidad' => 2],
            ]),
        ]);

        $respuesta->assertRedirect(route('admin.ordenes-compra.create'));

        $orden = OrdenCompra::where('numero_orden', 'OC-TEST-001')->firstOrFail();
        $this->assertSame('Distribuidora Drywall SAC', $orden->proveedor);
        $this->assertSame('', $orden->cliente_ref);
        $this->assertNull($orden->nro_factura);
        $this->assertNull($orden->empresa_transporte);
        $this->assertNull($orden->fecha_vencimiento);
        $this->assertSame(19.0, (float) $orden->total_soles);
    }
}
