<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\MovimientoAlmacen;
use App\Models\MovimientoCaja;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\Pos\CajaService;
use App\Services\Pos\PosVentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosVentaTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscenario(int $stock = 10, string $rol = 'admin'): array
    {
        $usuario = Usuario::create(['username' => 'cajero_'.uniqid(), 'password' => 'x', 'rol' => $rol]);
        $almacen = Almacen::create(['nombre' => 'Principal', 'activo' => true]);
        $producto = Producto::create(['nombre' => 'Placa Drywall 1/2"', 'codigo' => 'DRY-001', 'precio_venta' => 11.80]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => $stock]);

        $caja = Caja::create(['nombre' => 'Caja 01', 'activo' => true]);
        $sesion = app(CajaService::class)->abrir($usuario, $caja->id, 100);

        return compact('usuario', 'almacen', 'producto', 'caja', 'sesion');
    }

    public function test_venta_normal_registra_todo_correctamente(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario();

        $venta = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 23.60]],
            'pos_token' => 'tok-'.uniqid(),
        ], $usuario);

        $this->assertSame('pos', $venta->canal);
        $this->assertSame('activa', $venta->estado);
        $this->assertSame('pagada', $venta->estado_cobro);
        $this->assertSame(1, VentaDetalle::where('venta_id', $venta->id)->count());
        $this->assertSame(1, VentaPago::where('venta_id', $venta->id)->count());
        $this->assertSame(1, MovimientoAlmacen::where('producto_id', $producto->id)->where('tipo', 'salida')->count());

        $stock = StockAlmacen::where('producto_id', $producto->id)->where('almacen_id', $almacen->id)->first();
        $this->assertSame(8, $stock->stock);
        $this->assertSame(8, $producto->fresh()->stock);
    }

    public function test_boleta_genera_serie_y_correlativo_interno(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario();

        $venta = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
            'pos_token' => 'tok-'.uniqid(),
        ], $usuario);

        $this->assertSame('B001', $venta->n_seri);
        $this->assertSame(8, strlen($venta->n_comp));
    }

    public function test_factura_con_cliente_seleccionado(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario();

        $cliente = Cliente::create([
            'tipo_documento' => 'RUC', 'numero_documento' => '20612217697',
            'nombres' => 'Casa Drywall Cliente SAC', 'direccion' => 'Av. Test 123',
        ]);

        $venta = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen->id,
            'tipcomp' => '01',
            'cliente_id' => $cliente->id,
            'n_ruc' => '20612217697',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
            'pos_token' => 'tok-'.uniqid(),
        ], $usuario);

        $this->assertSame('F001', $venta->n_seri);
        $this->assertSame($cliente->id, $venta->cliente_id);
        $this->assertSame('Casa Drywall Cliente SAC', $venta->cliente_nombre);
    }

    public function test_stock_insuficiente_no_escribe_nada_ni_siquiera_parcialmente(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 1);

        try {
            app(PosVentaService::class)->procesar([
                'almacen_id' => $almacen->id,
                'tipcomp' => '03',
                'items' => [['producto_id' => $producto->id, 'cantidad' => 5]],
                'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 59.00]],
                'pos_token' => 'tok-'.uniqid(),
            ], $usuario);
            $this->fail('Debió lanzar ValidationException por stock insuficiente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        // Nada quedó escrito: la excepción se lanza dentro de la transacción,
        // así que todo se revierte (venta, detalle, pagos, stock, movimiento).
        $this->assertSame(0, Venta::count());
        $this->assertSame(0, VentaDetalle::count());
        $this->assertSame(0, VentaPago::count());
        $this->assertSame(0, MovimientoAlmacen::count());
        $this->assertSame(1, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
    }

    public function test_caja_cerrada_rechaza_antes_de_escribir(): void
    {
        $usuario = Usuario::create(['username' => 'sin_caja_'.uniqid(), 'password' => 'x', 'rol' => 'admin']);
        $almacen = Almacen::create(['nombre' => 'Principal', 'activo' => true]);
        $producto = Producto::create(['nombre' => 'Producto X', 'precio_venta' => 10]);
        StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => 5]);

        $this->expectException(ValidationException::class);

        try {
            app(PosVentaService::class)->procesar([
                'almacen_id' => $almacen->id,
                'tipcomp' => '03',
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 10]],
                'pos_token' => 'tok-'.uniqid(),
            ], $usuario);
        } finally {
            $this->assertSame(0, Venta::count());
        }
    }

    public function test_almacen_no_seleccionado_es_rechazado(): void
    {
        ['usuario' => $usuario, 'producto' => $producto] = $this->crearEscenario();

        $this->expectException(ValidationException::class);

        app(PosVentaService::class)->procesar([
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
            'pos_token' => 'tok-'.uniqid(),
        ], $usuario);
    }

    public function test_pago_insuficiente_es_rechazado(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario();

        try {
            app(PosVentaService::class)->procesar([
                'almacen_id' => $almacen->id,
                'tipcomp' => '03',
                'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
                'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 5]],
                'pos_token' => 'tok-'.uniqid(),
            ], $usuario);
            $this->fail('Debió lanzar ValidationException por pago insuficiente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pagos', $e->errors());
        }

        $this->assertSame(0, Venta::count());
    }

    public function test_pago_mixto_registra_un_venta_pago_y_un_movimiento_de_caja_por_metodo(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto, 'sesion' => $sesion] = $this->crearEscenario();

        $venta = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [
                ['metodo_pago' => 'Efectivo', 'monto' => 6.80],
                ['metodo_pago' => 'Yape', 'monto' => 5.00],
            ],
            'pos_token' => 'tok-'.uniqid(),
        ], $usuario);

        $this->assertSame(2, VentaPago::where('venta_id', $venta->id)->count());
        $this->assertSame('Mixto', $venta->metodo_pago);
        $this->assertSame(2, MovimientoCaja::where('sesion_caja_id', $sesion->id)->where('tipo', 'venta')->count());
    }

    public function test_solo_admin_puede_aplicar_descuento(): void
    {
        ['usuario' => $admin, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(rol: 'admin');

        $ventaConDescuento = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'descuento_pct' => 10,
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 20]],
            'pos_token' => 'tok-'.uniqid(),
        ], $admin);

        $detalle = VentaDetalle::where('venta_id', $ventaConDescuento->id)->first();
        $this->assertSame('10.00', $detalle->descuento_pct);
        $this->assertGreaterThan(0, (float) $ventaConDescuento->descuento_total);

        // Un rol no-admin manda el mismo descuento, pero el servidor lo ignora.
        ['usuario' => $secretaria, 'almacen' => $almacen2, 'producto' => $producto2] = $this->crearEscenario(rol: 'secretaria');

        $ventaSinDescuento = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen2->id,
            'tipcomp' => '03',
            'descuento_pct' => 10,
            'items' => [['producto_id' => $producto2->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
            'pos_token' => 'tok-'.uniqid(),
        ], $secretaria);

        $this->assertSame('0.00', (string) $ventaSinDescuento->descuento_total);
    }

    public function test_doble_clic_con_el_mismo_token_no_duplica_la_venta(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario();

        $token = 'tok-doble-'.uniqid();
        $payload = [
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
            'pos_token' => $token,
        ];

        $primera = app(PosVentaService::class)->procesar($payload, $usuario);
        $segunda = app(PosVentaService::class)->procesar($payload, $usuario);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, Venta::count());
        // El stock solo se descontó una vez, no dos.
        $this->assertSame(9, StockAlmacen::where('producto_id', $producto->id)->value('stock'));
    }

    public function test_dos_cajeros_vendiendo_el_ultimo_stock_solo_uno_gana(): void
    {
        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario(stock: 1);

        $payload = [
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
        ];

        app(PosVentaService::class)->procesar($payload + ['pos_token' => 'tok-1-'.uniqid()], $usuario);

        try {
            app(PosVentaService::class)->procesar($payload + ['pos_token' => 'tok-2-'.uniqid()], $usuario);
            $this->fail('El segundo cobro debió fallar: ya no quedaba stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        $this->assertSame(1, Venta::count());
    }

    public function test_venta_se_registra_aunque_api_go_este_caida(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        ['usuario' => $usuario, 'almacen' => $almacen, 'producto' => $producto] = $this->crearEscenario();

        // La emisión SUNAT la dispara el controller, no el servicio — se
        // prueba aquí que el servicio en sí no depende de eso para nada.
        $venta = app(PosVentaService::class)->procesar([
            'almacen_id' => $almacen->id,
            'tipcomp' => '03',
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'pagos' => [['metodo_pago' => 'Efectivo', 'monto' => 11.80]],
            'pos_token' => 'tok-'.uniqid(),
        ], $usuario);

        $this->assertNotNull($venta->id);
        $this->assertNull($venta->estado_factura);
    }
}
