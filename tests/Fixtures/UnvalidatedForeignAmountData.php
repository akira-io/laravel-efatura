<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Akira\Efatura\Casts\ForeignMoneyCast;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class UnvalidatedForeignAmountData extends Data
{
    public function __construct(
        #[WithCast(ForeignMoneyCast::class)]
        public readonly Money $value,
        public readonly ?string $currencyCode = null,
    ) {}
}
