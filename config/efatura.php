<?php

declare(strict_types=1);

return [
    'transmitter' => [
        'nif' => env('EFATURA_TRANSMITTER_NIF', ''),
        'led' => env('EFATURA_TRANSMITTER_LED', ''),
        'key' => env('EFATURA_TRANSMITTER_KEY', ''),
    ],
    'middleware' => [
        'base_url'    => env('EFATURA_MIDDLEWARE_BASE_URL', 'https://middleware.example'),
        'environment' => env('EFATURA_MIDDLEWARE_ENV', 'sandbox'),
    ],
];
