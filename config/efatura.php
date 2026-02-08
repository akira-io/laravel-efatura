<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Environment;

return [
    /*
    |--------------------------------------------------------------------------
    | Transmitter
    |--------------------------------------------------------------------------
    |
    | Identification data of the issuing entity.
    |
    */
    'transmitter' => [
        'nif' => env('EFATURA_TRANSMITTER_NIF', ''),
        'led' => env('EFATURA_TRANSMITTER_LED', ''),
        'key' => env('EFATURA_TRANSMITTER_KEY', ''),
    ],
    /*
    |--------------------------------------------------------------------------
    | Software
    |--------------------------------------------------------------------------
    |
    | DNRE requires a software identification block for transmission.
    | These values must be explicitly provided by the issuer.
    |
    */
    'software' => [
        'code'    => env('EFATURA_SOFTWARE_CODE'),
        'name'    => env('EFATURA_SOFTWARE_NAME'),
        'version' => env('EFATURA_SOFTWARE_VERSION'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware base URL is constant across environments. The environment
    | selection is performed using repository codes only.
    |
    | Supported environment values: PRODUCTION (1), HOMOLOGATION (2), TEST (3).
    | The default is TEST.
    |
    */
    'middleware' => [
        'base_url'    => env('EFATURA_MIDDLEWARE_BASE_URL', 'https://middleware.example'),
        'environment' => env('EFATURA_ENVIRONMENT', Environment::TEST),
    ],
];
