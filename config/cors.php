<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://sigva.unitepc.pro',
        'https://api.sigva.unitepc.pro',
        'https://sigeth.unitepc.pro',
        'https://api.sigeth.unitepc.pro',
        'https://postulaciones.unitepc.pro',
        'https://api.sispo.unitepc.pro',
        'https://sigva.xpertiaplus.com',
        'https://sigeth.xpertiaplus.com',
        'http://localhost:9000',
        'http://localhost:9001',
        'http://localhost:9002',
        'http://localhost:3000',
        'http://127.0.0.1:9000',
        'http://127.0.0.1:9001',
        'http://127.0.0.1:9002',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => true,

];
