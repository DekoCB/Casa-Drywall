<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Claude — análisis de facturas en PDF del módulo de Facturas.
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    /*
    | apis.net.pe — consulta pública de RUC (SUNAT) y DNI (RENIEC).
    | El token es opcional (gratuito, sube el límite de peticiones diarias).
    */
    'apisperu' => [
        'token' => env('APISPERU_TOKEN'),
    ],

    /*
    | API-GO — facturación electrónica real (SUNAT, vía Greenter). Servicio
    | Laravel independiente, corre aparte de este proyecto.
    */
    'api_go' => [
        'base_url' => env('API_GO_BASE_URL', 'http://127.0.0.1:8001/api/v1'),
        'token' => env('API_GO_TOKEN'),
        'company_id' => env('API_GO_COMPANY_ID', 1),
        'branch_id' => env('API_GO_BRANCH_ID', 1),
    ],

];
