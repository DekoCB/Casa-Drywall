<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Marca;
use App\Models\MovimientoAlmacen;
use App\Models\OrdenCompra;
use App\Models\Personal;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de ejemplo para que el panel se vea poblado en desarrollo/demo —
 * NO se ejecuta desde `DatabaseSeeder` a propósito, para que nunca corra
 * por accidente contra producción. Se corre a mano:
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 *
 * Reutiliza el mismo mecanismo real de `VentaController::storeFactura()`
 * (Cobranza -> Cobranza::reflejarEnVentas() crea la Venta) para Boletas y
 * Facturas, así generan cobranza de verdad; Cotización se crea directo,
 * sin cobranza, igual que ya corrige el código real.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = $this->categorias();
        $marcas = $this->marcas();
        $almacenes = Almacen::where('activo', true)->orderBy('id')->get();

        if ($almacenes->isEmpty()) {
            $almacenes = collect([
                Almacen::create(['nombre' => 'Almacén Principal', 'activo' => true]),
                Almacen::create(['nombre' => 'Almacén Pisco', 'activo' => true]),
            ]);
        }

        $productos = $this->productos($categorias, $marcas, $almacenes);
        $proveedores = $this->proveedores();
        $clientes = $this->clientes();
        $this->personal();
        $this->ordenesCompra($proveedores);
        $this->ventas($clientes, $productos);

        $this->command?->info('Datos de ejemplo cargados.');
    }

    /** @return array<string,Categoria> */
    private function categorias(): array
    {
        $nombres = [
            'Planchas de Drywall' => 'Planchas de yeso para tabiquería y cielo raso',
            'Perfiles Metálicos' => 'Rieles, parantes y angulares de acero galvanizado',
            'Tornillería y Fijaciones' => 'Tornillos, tarugos y anclajes',
            'Masillas y Compuestos' => 'Pastas y compuestos para junta',
            'Cintas y Selladores' => 'Cintas de papel, malla y selladores acrílicos',
            'Herramientas' => 'Herramienta manual y eléctrica para instalación',
            'Aislantes Térmicos' => 'Lana de vidrio, lana mineral y aislantes acústicos',
            'Pintura y Acabados' => 'Pinturas, bases y empastes',
        ];

        $creadas = [];

        foreach ($nombres as $nombre => $descripcion) {
            $creadas[$nombre] = Categoria::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        return $creadas;
    }

    /** @return array<string,Marca> */
    private function marcas(): array
    {
        $nombres = ['Volcan', 'Trupan', 'Eternit', 'Knauf', 'Gyplac', '3M'];
        $creadas = [];

        foreach ($nombres as $nombre) {
            $creadas[$nombre] = Marca::firstOrCreate(['nombre' => $nombre]);
        }

        return $creadas;
    }

    /**
     * @param  array<string,Categoria>  $categorias
     * @param  array<string,Marca>  $marcas
     * @return \Illuminate\Support\Collection<int,Producto>
     */
    private function productos(array $categorias, array $marcas, $almacenes)
    {
        // [codigo, nombre, categoria, marca, precio_compra, precio_venta, stock_minimo]
        $catalogo = [
            ['PL-001', 'Plancha Drywall Standard 1/2" 1.20x2.40m', 'Planchas de Drywall', 'Volcan', 22.00, 32.00, 40],
            ['PL-002', 'Plancha Drywall Standard 5/8" 1.20x2.40m', 'Planchas de Drywall', 'Volcan', 26.00, 38.00, 30],
            ['PL-003', 'Plancha Drywall RH Resistente Humedad 1/2" 1.20x2.40m', 'Planchas de Drywall', 'Knauf', 34.00, 49.00, 20],
            ['PL-004', 'Plancha Drywall RF Resistente Fuego 5/8" 1.20x2.40m', 'Planchas de Drywall', 'Knauf', 38.00, 55.00, 15],
            ['PL-005', 'Plancha Superboard 6mm 1.20x2.40m', 'Planchas de Drywall', 'Eternit', 45.00, 65.00, 10],
            ['PL-006', 'Plancha Superboard 8mm 1.20x2.40m', 'Planchas de Drywall', 'Eternit', 55.00, 78.00, 8],
            ['PF-001', 'Perfil Riel 90mm x 3m', 'Perfiles Metálicos', 'Trupan', 12.50, 18.00, 60],
            ['PF-002', 'Perfil Parante 90mm x 3m', 'Perfiles Metálicos', 'Trupan', 13.50, 19.50, 60],
            ['PF-003', 'Perfil Riel 70mm x 3m', 'Perfiles Metálicos', 'Trupan', 10.50, 15.50, 40],
            ['PF-004', 'Perfil Parante 70mm x 3m', 'Perfiles Metálicos', 'Trupan', 11.50, 16.90, 40],
            ['PF-005', 'Perfil Angular 25x25mm x 3m', 'Perfiles Metálicos', 'Trupan', 7.00, 10.50, 30],
            ['PF-006', 'Perfil Omega 3m', 'Perfiles Metálicos', 'Trupan', 9.00, 13.50, 25],
            ['TR-001', 'Tornillo Drywall Punta Fina 1" (caja x1000)', 'Tornillería y Fijaciones', 'Gyplac', 18.00, 27.00, 15],
            ['TR-002', 'Tornillo Drywall Punta Fina 1 5/8" (caja x1000)', 'Tornillería y Fijaciones', 'Gyplac', 21.00, 31.00, 15],
            ['TR-003', 'Tornillo Drywall Punta Broca 1/2" (caja x1000)', 'Tornillería y Fijaciones', 'Gyplac', 19.00, 28.50, 12],
            ['TR-004', 'Tornillo Autorroscante 1" (caja x500)', 'Tornillería y Fijaciones', 'Gyplac', 14.00, 21.00, 10],
            ['TR-005', 'Tarugo Fischer 8mm (bolsa x100)', 'Tornillería y Fijaciones', 'Gyplac', 9.50, 15.00, 20],
            ['TR-006', 'Clavo con Roldana 1" (kg)', 'Tornillería y Fijaciones', 'Gyplac', 6.50, 10.00, 25],
            ['MC-001', 'Pasta para Junta 28kg', 'Masillas y Compuestos', 'Volcan', 38.00, 55.00, 20],
            ['MC-002', 'Pasta para Junta 5kg', 'Masillas y Compuestos', 'Volcan', 12.00, 18.50, 25],
            ['MC-003', 'Compuesto Base Coat 20kg', 'Masillas y Compuestos', 'Knauf', 42.00, 60.00, 15],
            ['MC-004', 'Sellador Acrílico 300ml', 'Masillas y Compuestos', '3M', 8.50, 13.90, 30],
            ['CN-001', 'Cinta de Papel para Junta 75m', 'Cintas y Selladores', 'Volcan', 6.00, 9.50, 40],
            ['CN-002', 'Cinta Malla Autoadhesiva 90m', 'Cintas y Selladores', '3M', 15.00, 22.00, 20],
            ['CN-003', 'Cinta de Embalaje 48mm', 'Cintas y Selladores', '3M', 2.50, 4.50, 50],
            ['HR-001', 'Espátula 6"', 'Herramientas', 'Trupan', 9.00, 15.00, 10],
            ['HR-002', 'Espátula 10"', 'Herramientas', 'Trupan', 13.00, 21.00, 10],
            ['HR-003', 'Llana Dentada', 'Herramientas', 'Trupan', 16.00, 25.00, 8],
            ['HR-004', 'Sierra para Drywall', 'Herramientas', 'Trupan', 22.00, 34.00, 8],
            ['HR-005', 'Atornillador Eléctrico 220V', 'Herramientas', '3M', 180.00, 259.00, 3],
            ['HR-006', 'Nivel de Burbuja 60cm', 'Herramientas', 'Trupan', 25.00, 39.00, 6],
            ['HR-007', 'Escuadra Metálica 90cm', 'Herramientas', 'Trupan', 28.00, 42.00, 6],
            ['AI-001', 'Lana de Vidrio 2" x 1.20 x 15m', 'Aislantes Térmicos', 'Knauf', 65.00, 95.00, 10],
            ['AI-002', 'Lana Mineral 3" (rollo)', 'Aislantes Térmicos', 'Knauf', 78.00, 112.00, 8],
            ['AI-003', 'Aislante Acústico 1" (plancha)', 'Aislantes Térmicos', 'Eternit', 32.00, 48.00, 12],
            ['PT-001', 'Pintura Látex Blanco 1 Galón', 'Pintura y Acabados', 'Volcan', 28.00, 42.00, 15],
            ['PT-002', 'Base Selladora 1 Galón', 'Pintura y Acabados', 'Volcan', 24.00, 36.00, 15],
            ['PT-003', 'Empaste 20kg', 'Pintura y Acabados', 'Volcan', 35.00, 52.00, 10],
        ];

        $creados = collect();

        foreach ($catalogo as [$codigo, $nombre, $catNombre, $marcaNombre, $compra, $venta, $minimo]) {
            $producto = Producto::firstOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'categoria_id' => $categorias[$catNombre]->id,
                    'marca_id' => $marcas[$marcaNombre]->id,
                    'precio_compra' => $compra,
                    'precio_venta' => $venta,
                    'stock_minimo' => $minimo,
                    'stock' => 0,
                    'estado' => 'activo',
                ]
            );

            if ($producto->stockPorAlmacen()->exists()) {
                $creados->push($producto);

                continue;
            }

            // Un tercio de los productos queda apenas por debajo del mínimo,
            // a propósito, para que el aviso de "stock bajo" tenga contenido real.
            $bajoStock = $creados->count() % 3 === 0;
            $totalStock = $bajoStock
                ? max(0, $minimo - random_int(1, 5))
                : $minimo + random_int($minimo, $minimo * 3);

            $porAlmacen = $this->repartirEntreAlmacenes($totalStock, $almacenes->count());

            foreach ($almacenes as $i => $almacen) {
                $cantidad = $porAlmacen[$i] ?? 0;

                StockAlmacen::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'stock' => $cantidad]);

                if ($cantidad > 0) {
                    MovimientoAlmacen::create([
                        'producto_id' => $producto->id,
                        'almacen_id' => $almacen->id,
                        'tipo' => 'entrada',
                        'cantidad' => $cantidad,
                        'stock_anterior' => 0,
                        'stock_nuevo' => $cantidad,
                        'motivo' => 'Carga inicial de inventario',
                        'estado' => 'entregado',
                        'created_at' => Carbon::now()->subMonths(6),
                    ]);
                }
            }

            $producto->recalcularStock();
            $creados->push($producto);
        }

        return $creados;
    }

    /** @return array<int,int> */
    private function repartirEntreAlmacenes(int $total, int $n): array
    {
        if ($n <= 1) {
            return [$total];
        }

        $primero = (int) round($total * 0.7);

        return [$primero, $total - $primero];
    }

    /** @return \Illuminate\Support\Collection<int,Proveedor> */
    private function proveedores()
    {
        $datos = [
            ['20512345671', 'Distribuidora Volcan S.A.C.', '956123456'],
            ['20512345672', 'Corporación Aceros del Sur S.A.', '956123457'],
            ['20512345673', 'Comercial Ferretera Ica E.I.R.L.', '956123458'],
            ['20512345674', 'Importadora Knauf Perú S.A.C.', '956123459'],
            ['20512345675', 'Grupo Constructor Lima S.A.C.', '956123460'],
        ];

        return collect($datos)->map(fn ($d) => Proveedor::firstOrCreate(
            ['ruc' => $d[0]],
            ['razon_social' => $d[1], 'telefono' => $d[2], 'dias_credito' => 30, 'estado' => 'activo']
        ));
    }

    /** @return \Illuminate\Support\Collection<int,Cliente> */
    private function clientes()
    {
        $datos = [
            ['20601111111', 'Constructora Andina S.A.C.'],
            ['20601111112', 'Inmobiliaria Los Pinos S.A.C.'],
            ['20601111113', 'Grupo Edificaciones del Sur E.I.R.L.'],
            ['20601111114', 'Constructora Vega Hermanos S.R.L.'],
            ['20601111115', 'Inversiones Inmobiliarias Pisco S.A.C.'],
            ['20601111116', 'Contratistas Generales Ica S.A.C.'],
            ['20601111117', 'Remodelaciones y Acabados JR E.I.R.L.'],
            ['20601111118', 'Constructora Beatita S.A.C.'],
        ];

        return collect($datos)->map(fn ($d) => Cliente::firstOrCreate(
            ['numero_documento' => $d[0]],
            ['tipo_documento' => 'RUC', 'nombre_empresa' => $d[1], 'nombres' => $d[1], 'estado' => 'activo', 'distrito' => 'Pisco', 'provincia' => 'Pisco', 'departamento' => 'Ica']
        ));
    }

    private function personal(): void
    {
        $datos = [
            ['45111111', 'Jimena', 'Torres Salas', 'Vendedora', 'Ventas'],
            ['45111112', 'Carlos', 'Huamán Ríos', 'Almacenero', 'Inventario'],
            ['45111113', 'Rosa', 'Fernández Paz', 'Asistente Contable', 'Administración'],
            ['45111114', 'Luis', 'Quispe Mamani', 'Chofer / Reparto', 'Logística'],
        ];

        foreach ($datos as [$dni, $nombres, $apellidos, $cargo, $area]) {
            Personal::firstOrCreate(
                ['dni' => $dni],
                [
                    'nombres' => $nombres, 'apellidos' => $apellidos, 'cargo' => $cargo, 'area' => $area,
                    'sueldo' => random_int(1200, 2500), 'tipo_contrato' => 'Planilla',
                    'fecha_ingreso' => Carbon::now()->subMonths(random_int(2, 24)), 'estado' => 'activo',
                ]
            );
        }
    }

    private function ordenesCompra($proveedores): void
    {
        foreach (range(1, 5) as $i) {
            $proveedor = $proveedores[array_rand($proveedores->all())];
            $totalUsd = random_int(500, 3000);
            $tc = 3.78;

            OrdenCompra::create([
                'numero_orden' => 'OC-DEMO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'fecha' => Carbon::now()->subDays(random_int(5, 90)),
                'proveedor' => $proveedor->razon_social,
                'ruc' => $proveedor->ruc,
                'telefono' => $proveedor->telefono,
                'tc' => $tc,
                'total_usd' => $totalUsd,
                'total_soles' => round($totalUsd * $tc, 2),
                'estado' => ['Pendiente', 'En Tránsito', 'Recibido'][array_rand(['Pendiente', 'En Tránsito', 'Recibido'])],
                'condicion_pago' => 'Contado',
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Cliente>  $clientes
     * @param  \Illuminate\Support\Collection<int,Producto>  $productos
     */
    private function ventas($clientes, $productos): void
    {
        $secuencia = ['03' => 1, '01' => 1, 'NV' => 1, 'COT' => 1];

        for ($i = 0; $i < 55; $i++) {
            $fecha = Carbon::now()->subDays(random_int(0, 180));
            $cliente = $clientes[array_rand($clientes->all())];

            // 55% Boleta, 15% Factura, 15% Nota de Venta, 15% Cotización.
            $roll = random_int(1, 100);
            $tipcomp = match (true) {
                $roll <= 55 => '03',
                $roll <= 70 => '01',
                $roll <= 85 => 'NV',
                default => 'COT',
            };

            $lineas = $this->lineasAleatorias($productos);
            $subtotalLineas = collect($lineas)->sum(fn ($l) => $l['cantidad'] * $l['precio']);
            $base = round($subtotalLineas / 1.18, 2);
            $igv = round($subtotalLineas - $base, 2);

            $serie = ['03' => 'B001', '01' => 'F001', 'NV' => 'NV01', 'COT' => 'CT01'][$tipcomp];
            $numero = str_pad((string) $secuencia[$tipcomp]++, 8, '0', STR_PAD_LEFT);

            if ($tipcomp === 'COT') {
                $venta = Venta::create([
                    'fecha' => $fecha, 'tipcomp' => 'COT', 'n_seri' => $serie, 'n_comp' => $numero,
                    'tipo_comprobante' => 'Cotización', 'razonsocial' => $cliente->nombre_empresa,
                    'n_ruc' => $cliente->numero_documento, 'cliente_id' => $cliente->id,
                    'cliente_nombre' => $cliente->nombre_empresa,
                    'baseimp' => $base, 'igv' => $igv, 'total' => $subtotalLineas,
                    'exonerado' => 0, 'inafecto' => 0, 'moneda' => 'PEN', 'tipcambio' => 1,
                    'estado' => 'activa',
                ]);
            } else {
                // Igual mecanismo que `storeFactura()`: la Cobranza refleja
                // automáticamente la fila de `ventas` (Cobranza::booted()).
                $pagada = random_int(1, 100) <= 55;
                $venc = (clone $fecha)->addDays(30);

                $cobranza = new Cobranza([
                    'tipo' => $tipcomp === '03' ? 'BV' : 'FT',
                    'numero' => "{$serie}-{$numero}",
                    'fecha_emision' => $fecha,
                    'fecha_vencimiento' => $venc,
                    'cliente_nombre' => $cliente->nombre_empresa,
                    'cliente_id' => $cliente->id,
                    'monto_total' => $subtotalLineas,
                    'monto_pagado' => $pagada ? $subtotalLineas : 0,
                    'fecha_pago' => $pagada ? (clone $fecha)->addDays(random_int(1, 25)) : null,
                ]);
                $cobranza->recalcularEstado();
                $cobranza->save();

                $venta = Venta::where('cobranza_id', $cobranza->id)->firstOrFail();
                $venta->update([
                    'tipcomp' => $tipcomp,
                    'tipo_comprobante' => $tipcomp === '03' ? 'Boleta de Venta' : 'Factura',
                    'n_seri' => $serie, 'n_comp' => $numero,
                    'n_ruc' => $cliente->numero_documento, 'cliente_ruc' => $cliente->numero_documento,
                    'baseimp' => $base, 'igv' => $igv, 'exonerado' => 0, 'inafecto' => 0,
                    'moneda' => 'PEN', 'tipcambio' => 1,
                    'estado_factura' => 'pendiente',
                ]);
            }

            foreach ($lineas as $linea) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $linea['producto']->id,
                    'prod_codigo' => $linea['producto']->codigo,
                    'prod_nombre' => $linea['producto']->nombre,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio'],
                    'subtotal' => round($linea['cantidad'] * $linea['precio'], 2),
                ]);
            }
        }
    }

    /** @param  \Illuminate\Support\Collection<int,Producto>  $productos */
    private function lineasAleatorias($productos): array
    {
        $n = random_int(1, 4);
        $elegidos = $productos->random(min($n, $productos->count()));

        return $elegidos->map(fn (Producto $p) => [
            'producto' => $p,
            'cantidad' => random_int(1, 15),
            'precio' => (float) $p->precio_venta,
        ])->all();
    }
}
