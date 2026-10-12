<?php

declare(strict_types=1);

return [
    'exchange_rates' => [
        'provider' => env('EFATURA_EXCHANGE_RATE_PROVIDER', 'fixed'),
        'bcv' => [
            'rate_type' => env('EFATURA_BCV_RATE_TYPE', 'buy'),
            'max_publication_age_days' => env('EFATURA_BCV_MAX_PUBLICATION_AGE_DAYS', 3),
        ],
        'world_bank' => [
            'economies' => ['EUR' => 'EMU'],
            'observation_year' => env('EFATURA_WORLD_BANK_OBSERVATION_YEAR', 'previous'),
        ],
        'fixed' => [
            'rates' => ['EUR' => '110.265'],
            'rate_type' => 'custom',
            'name' => 'Playground fixed rate',
        ],
        'callback' => null,
    ],
];
