<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use const STR_PAD_LEFT;

final class LimitFixtures
{
    /**
     * @return list<string>
     */
    public static function iuds(int $count): array
    {
        return array_map(static fn (int $number): string => 'CV12610021' . str_pad((string) $number, 35, '0', STR_PAD_LEFT), range(1, $count));
    }

    /**
     * @return list<array{value: string, currencyCode: string, exchangeRate: string}>
     */
    public static function alternativeAmounts(int $count): array
    {
        return array_fill(0, $count, ['value' => '1', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265']);
    }
}
