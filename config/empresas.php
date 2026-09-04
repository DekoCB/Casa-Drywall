<?php

/**
 * Empresas que el sistema puede operar, elegidas antes del login (ver
 * `App\Http\Middleware\SeleccionarEmpresa`). Cada una tiene su propia
 * base de datos y, opcionalmente, su propia identidad/color de marca.
 *
 * `conexion`/`empresa`/`color` en null significa "no sobreescribir nada":
 * Casa Drywall sigue usando exactamente lo que ya había (conexión
 * `mysql` por defecto, `config/rentaltech.php` tal cual, sin cambio de
 * color) — evita duplicar esos datos en dos lugares.
 */
return [
    'default' => 'casadrywall',

    'lista' => [
        'casadrywall' => [
            'nombre' => 'Casa Drywall',
            'conexion' => null,
            'color' => null,
            'sunat_habilitado' => true,
            'empresa' => null,
        ],
        'jitk' => [
            'nombre' => 'Jitk',
            'conexion' => 'jitk',
            'color' => '#0180fe',
            'sunat_habilitado' => false,
            'empresa' => [
                'razon_social' => 'Jitk',
                'ruc' => '20613590511',
                'direccion' => 'Urb granados etapa ll mz c lot 09',
                'telefono' => '937319750',
                'email' => 'importacionesjitk@gmail.com',
            ],
        ],
    ],
];
