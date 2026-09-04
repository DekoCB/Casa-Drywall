<?php

/**
 * Menú lateral del panel administrativo.
 *
 * Reproduce exactamente las secciones, el orden, los iconos y los badges
 * del sidebar de `administrador/index.php` del proyecto original.
 */
$icon = fn (string $paths) => '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">'.$paths.'</svg>';

$iconos = [
    'dashboard' => $icon('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'),
    'usuarios' => $icon('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
    'proveedores' => $icon('<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>'),
    'documento' => $icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'),
    'carrito' => $icon('<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>'),
    'bolsa' => $icon('<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>'),
    'dinero' => $icon('<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'),
    'calendario' => $icon('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'),
    'subida' => $icon('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'),
    'bajada' => $icon('<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>'),
    'caja' => $icon('<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'),
    'regalo' => $icon('<path d="M20 12v9H4v-9"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'),
    'camion' => $icon('<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>'),
    'grafico' => $icon('<path d="M3 3v18h18"/><rect x="7" y="13" width="3" height="5"/><rect x="12" y="9" width="3" height="9"/><rect x="17" y="5" width="3" height="13"/>'),
    'pos' => $icon('<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'),
    'inventario' => $icon('<path d="M21 8V21H3V8"/><path d="M1 3h22v5H1z"/><line x1="10" y1="12" x2="14" y2="12"/>'),
    'local' => $icon('<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h1"/><path d="M14 9h1"/><path d="M9 13h1"/><path d="M14 13h1"/><path d="M10 21v-4h4v4"/>'),
];

return [
    'admin' => [
        'Principal' => [
            ['route' => 'admin.index', 'label' => 'Dashboard', 'icon' => $iconos['dashboard'], 'active' => ['admin.index', 'admin.galonaje.dashboard']],
        ],

        'Gestión Comercial' => [
            ['route' => 'admin.clientes.index',     'label' => 'Clientes',     'icon' => $iconos['usuarios']],
            ['route' => 'admin.locales.index',      'label' => 'Locales',      'icon' => $iconos['local']],
            [
                'route' => 'admin.ventas.index', 'label' => 'Ventas', 'icon' => $iconos['carrito'],
                'submenu' => [
                    ['route' => 'admin.ventas.index', 'query' => ['tipcomp' => 'COT'], 'label' => 'Cotizaciones',
                        'crearRoute' => 'admin.ventas.factura.create', 'crearQuery' => ['tipo' => 'COT']],
                    ['route' => 'admin.ventas.index', 'query' => ['tipcomp' => 'NV'], 'label' => 'Notas de Venta',
                        'crearRoute' => 'admin.ventas.factura.create', 'crearQuery' => ['tipo' => 'NV']],
                    ['route' => 'admin.ventas.index', 'query' => ['tipcomp' => '03'], 'label' => 'Boletas',
                        'crearRoute' => 'admin.ventas.factura.create', 'crearQuery' => ['tipo' => '03']],
                    ['route' => 'admin.ventas.index', 'query' => ['tipcomp' => '01'], 'label' => 'Facturas',
                        'crearRoute' => 'admin.ventas.factura.create', 'crearQuery' => ['tipo' => '01']],
                    ['route' => 'admin.pedidos.index', 'label' => 'Pedidos',
                        'crearRoute' => 'admin.pedidos.index', 'crearQuery' => ['crear' => 1]],
                    ['divider' => 'Gestión SUNAT'],
                    ['route' => 'admin.ventas.index', 'query' => ['estado_factura' => 'no_enviado'], 'label' => 'No enviados'],
                    ['route' => 'admin.ventas.index', 'query' => ['estado' => 'cancelada'], 'label' => 'Anulaciones'],
                ],
            ],
            [
                'route' => 'admin.pos.index', 'label' => 'POS', 'icon' => $iconos['pos'],
                'submenu' => [
                    ['route' => 'admin.pos.index',  'label' => 'Punto de Venta'],
                    ['route' => 'admin.caja.index', 'label' => 'Cajas'],
                ],
            ],
        ],

        'Compras & Documentos' => [
            [
                'route' => 'admin.ordenes-compra.index', 'label' => 'Compras', 'icon' => $iconos['bolsa'],
                'submenu' => [
                    ['route' => 'admin.ordenes-compra.index', 'label' => 'Listado de compras',
                        'crearRoute' => 'admin.ordenes-compra.create'],
                    ['route' => 'admin.egresos.index', 'query' => ['tipo' => 'diversos'], 'label' => 'Gastos diversos',
                        'crearRoute' => 'admin.egresos.index', 'crearQuery' => ['tipo' => 'diversos', 'crear' => 1]],
                    ['route' => 'admin.proveedores.index', 'label' => 'Proveedores'],
                    ['route' => 'admin.cotizaciones-proveedor.index', 'label' => 'Solicitar cotización',
                        'crearRoute' => 'admin.cotizaciones-proveedor.index', 'crearQuery' => ['crear' => 1]],
                    ['route' => 'admin.activos-fijos.index', 'label' => 'Activos fijos'],
                    ['route' => 'admin.activos-fijos.index', 'query' => ['crear' => 1], 'label' => 'Comprar activo fijo'],
                    ['route' => 'admin.liquidaciones-compra.index', 'label' => 'Liquidación de compra',
                        'crearRoute' => 'admin.liquidaciones-compra.index', 'crearQuery' => ['crear' => 1]],
                    ['route' => 'admin.ordenes-compra.index', 'label' => 'Orden de compra',
                        'crearRoute' => 'admin.ordenes-compra.create'],
                ],
            ],
            ['route' => 'admin.facturas.index', 'label' => 'Facturas', 'icon' => $iconos['documento'], 'badge' => 'GP', 'badge_class' => 'b-violet'],
        ],

        'Finanzas' => [
            ['route' => 'admin.cobranzas.index',       'label' => 'Cobranzas',          'icon' => $iconos['dinero']],
            ['route' => 'admin.historial-pagos.index', 'label' => 'Historial de Pagos', 'icon' => $iconos['calendario']],
            ['route' => 'admin.ingresos.index',        'label' => 'Ingresos',           'icon' => $iconos['subida']],
            ['route' => 'admin.egresos.index',         'label' => 'Egresos',            'icon' => $iconos['bajada']],
        ],

        'Inventario' => [
            [
                'route' => 'admin.productos.index', 'label' => 'Productos/Servicios', 'icon' => $iconos['caja'],
                'submenu' => [
                    ['route' => 'admin.productos.index', 'label' => 'Productos', 'active' => [
                        'admin.productos.index', 'admin.productos.categorias',
                        'admin.productos.presentaciones', 'admin.productos.almacenes',
                    ]],
                    ['route' => 'admin.categorias.index', 'label' => 'Categorías'],
                    ['route' => 'admin.marcas.index', 'label' => 'Marcas'],
                ],
            ],
            [
                'route' => 'admin.inventario.movimientos', 'label' => 'Inventario', 'icon' => $iconos['inventario'],
                'submenu' => [
                    ['route' => 'admin.inventario.movimientos', 'label' => 'Movimientos'],
                    ['route' => 'admin.inventario.movimientos', 'query' => ['tipo' => 'traslado'], 'label' => 'Traslados'],
                    ['route' => 'admin.inventario.movimientos', 'query' => ['tipo' => 'devolucion'], 'label' => 'Devolución a proveedor'],
                    ['route' => 'admin.inventario.kardex', 'label' => 'Reporte Kardex'],
                    ['route' => 'admin.inventario.reporte', 'label' => 'Reporte Inventario'],
                    ['route' => 'admin.inventario.kardex-valorizado', 'label' => 'Kardex valorizado'],
                ],
            ],
            ['route' => 'admin.merch.index',      'label' => 'Merch',      'icon' => $iconos['regalo']],
        ],

        'Logística' => [
            ['route' => 'admin.transporte.index', 'label' => 'Transporte',        'icon' => $iconos['camion']],
            ['route' => 'admin.guias.index',      'label' => 'Guías de Remisión', 'icon' => $iconos['bolsa'], 'badge' => 'New', 'badge_class' => 'b-cyan'],
        ],

        'Recursos Humanos' => [
            ['route' => 'admin.personal.index', 'label' => 'Personal', 'icon' => $iconos['usuarios']],
        ],

        'Análisis' => [
            ['route' => 'admin.reportes.index',           'label' => 'Reportes',           'icon' => $iconos['grafico']],
            ['route' => 'admin.galonaje.productos.index', 'label' => 'Galonaje Productos', 'icon' => $iconos['caja']],
        ],
    ],
];
