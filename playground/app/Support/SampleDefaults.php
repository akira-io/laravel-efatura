<?php

declare(strict_types=1);

namespace App\Support;

final class SampleDefaults
{
    /**
     * @return list<array{description: string, quantity: string, unit_price: string}>
     */
    public static function lines(): array
    {
        return [
            ['description' => 'Consulting hours', 'quantity' => '2', 'unit_price' => '1500'],
            ['description' => 'Printer paper (box)', 'quantity' => '3', 'unit_price' => '425.50'],
        ];
    }
}
