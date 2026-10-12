<?php

declare(strict_types=1);

return [
    'transport' => env('PLAYGROUND_TRANSPORT', 'fake'),

    'series' => env('PLAYGROUND_SERIES', 'PG'),

    'certificate' => [
        'disk' => env('EFATURA_CERTIFICATES_DISK', 'certificates'),
        'certificate_path' => env('EFATURA_CERTIFICATE_PATH', 'playground.crt'),
        'private_key_path' => env('EFATURA_PRIVATE_KEY_PATH', 'playground.key'),
        'valid_days' => 365,
    ],
];
