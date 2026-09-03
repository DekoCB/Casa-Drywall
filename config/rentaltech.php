<?php

return [
    /*
    | Contraseña maestra requerida para dar de alta usuarios nuevos.
    | En el proyecto original era la constante MASTER_PASSWORD de register.php.
    */
    'master_password' => env('RT_MASTER_PASSWORD', '654321'),

    /*
    | Datos de la empresa usados en comprobantes y guías.
    */
    'empresa' => [
        'razon_social' => env('RT_RAZON_SOCIAL', 'RENTAL TECH SAC'),
        'ruc' => env('RT_RUC', ''),
        'direccion' => env('RT_DIRECCION', ''),
        'telefono' => env('RT_TELEFONO', ''),
        'email' => env('RT_EMAIL', ''),
    ],

    /*
    | IGV vigente aplicado a las ventas.
    */
    'igv' => (float) env('RT_IGV', 0.18),

    /*
    | Tipo de cambio de respaldo para órdenes de compra. Es el valor que se
    | muestra mientras no haya respuesta de la cotización del día.
    */
    'tipo_cambio' => (float) env('RT_TIPO_CAMBIO', 3.75),

    /*
    | Tipo de cambio con el que llegan las facturas de GP Maquinarias.
    | Es un valor distinto al de las órdenes de compra.
    */
    'tipo_cambio_facturas' => (float) env('RT_TIPO_CAMBIO_FACTURAS', 3.44),

    /*
    | Datos que van fijos en toda orden de compra. En el original están
    | escritos como campos ocultos dentro del formulario.
    */
    'emisor_oc' => [
        'proveedor'    => 'RENTAL TECH SAC',
        'ruc'          => '20612189651',
        'telefono'     => '982018051',
        'correo'       => 'rafaelf.aguinaga@gmail.com',
        'direccion'    => 'CAR. CENTRAL KM. 100 SEC. CHUNCHUYACU',
        'distrito'     => 'SAN RAMON',
        'provincia'    => 'CHANCHAMAYO',
        'departamento' => 'JUNIN',
    ],

    /*
    | Cuentas bancarias que se muestran al pie de la Cotización, para que el
    | cliente pueda depositar directamente. Datos fijos de la empresa, igual
    | que `emisor_oc`.
    */
    'cuentas_bancarias' => [
        [
            'banco' => 'BBVA', 'abrev' => 'BBVA', 'moneda' => 'S/ Soles',
            'titular' => 'BBVA - CASA DRYWALL E.I.R.L.',
            'cuenta' => '0011-0241-0200859015-76',
            'cci' => '011-241-000200859015-76',
            'color' => '#0d47a1', 'bg' => '#e8f0fe',
        ],
        [
            'banco' => 'INTERBANK', 'abrev' => 'IBK', 'moneda' => 'S/ Soles',
            'titular' => 'INTERBANK - CASA DRYWALL E.I.R.L.',
            'cuenta' => '404-3006076405-14',
            'cci' => '003-404-003006076405-14',
            'color' => '#1b5e20', 'bg' => '#f0faf4',
        ],
    ],

    /*
    | API key de OpenRouter para el análisis de facturas con IA.
    */
    'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),

    /*
    | Botones rápidos de método de pago en el modal de cobranzas.
    | Al pulsarlos rellenan la observación del pago.
    */
    'metodos_pago' => [
        ['label' => 'Efectivo',  'color' => '#2e7d32', 'bg' => '#f0faf4', 'border' => '#c3e6d0',
         'svg' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        ['label' => 'Yape',      'color' => '#6a1b9a', 'bg' => '#f7f0fb', 'border' => '#d8bff0',
         'svg' => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>'],
        ['label' => 'Plin',      'color' => '#0277bd', 'bg' => '#e3f2fd', 'border' => '#90caf9',
         'svg' => '<rect x="5" y="2" width="14" height="20" rx="2"/><polyline points="9 11 12 14 15 11"/>'],
        ['label' => 'BCP',       'color' => '#b71c1c', 'bg' => '#fdf0f0', 'border' => '#e8b4b4',
         'svg' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>'],
        ['label' => 'Interbank', 'color' => '#1b5e20', 'bg' => '#f0faf4', 'border' => '#c3e6d0',
         'svg' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/>'],
        ['label' => 'BBVA',      'color' => '#0d47a1', 'bg' => '#e8f0fe', 'border' => '#90b4f5',
         'svg' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="7" cy="15" r="1" fill="currentColor"/>'],
    ],
];
