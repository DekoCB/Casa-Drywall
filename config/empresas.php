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

    // Video de fondo del selector de empresa cuando no se está sobre
    // ninguna tarjeta (ver resources/views/empresas/elegir.blade.php).
    'selector_video_default' => 'videos/login-bg-selector.mp4',

    'lista' => [
        'casadrywall' => [
            'nombre' => 'Casa Drywall',
            'conexion' => null,
            'color' => null,
            'sunat_habilitado' => true,
            'empresa' => null,
            // A diferencia de `color` (que sobreescribe la marca de TODA la
            // app cuando la empresa está activa), esto es solo la
            // presentación de su tarjeta en el selector previo al login.
            'selector' => [
                'color' => '#D9CB16',
                'on_color' => '#1A1A18',
                'logo' => 'img/Logo.png',
                'video' => 'videos/login-bg.mp4',
            ],
        ],
        'jitk' => [
            'nombre' => 'Jitk',
            'conexion' => 'jitk',
            'color' => '#0180fe',
            'color_secondary' => '#014a91',
            'sunat_habilitado' => false,
            'empresa' => [
                'razon_social' => 'Jitk',
                'ruc' => '20613590511',
                'direccion' => 'Urb granados etapa ll mz c lot 09',
                'telefono' => '937319750',
                'email' => 'importacionesjitk@gmail.com',
            ],
            'selector' => [
                'color' => '#0180fe',
                'on_color' => '#FFFFFF',
                'logo' => null,
                'video' => 'videos/login-bg-jitk.mp4',
            ],
        ],
    ],
];
