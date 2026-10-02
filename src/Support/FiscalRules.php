<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

final class FiscalRules
{
    /**
     * @return list<string>
     */
    public static function text(int $minimum, int $maximum): array
    {
        return ['string', "min:{$minimum}", "max:{$maximum}", 'regex:/\A[^\s]+(?: [^\s]+)*\z/u'];
    }

    /**
     * @return list<string>
     */
    public static function code(int $maximum = 50): array
    {
        return ['string', 'min:1', "max:{$maximum}", 'regex:/\A[^\s]+\z/u'];
    }
}
