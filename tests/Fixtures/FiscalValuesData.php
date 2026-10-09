<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Akira\Efatura\Casts\BigDecimalCast;
use Akira\Efatura\Casts\MoneyCast;
use Akira\Efatura\Transformers\BigDecimalTransformer;
use Akira\Efatura\Transformers\MoneyTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class FiscalValuesData extends Data
{
    public function __construct(
        #[WithCast(MoneyCast::class, 'CVE')]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $payable,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly Money $unitPrice,
        #[WithCast(BigDecimalCast::class, 5)]
        #[WithTransformer(BigDecimalTransformer::class, 5)]
        public readonly BigDecimal $exchangeRate,
        #[WithCast(BigDecimalCast::class, 3)]
        #[WithTransformer(BigDecimalTransformer::class, 3)]
        public readonly BigDecimal $taxPercentage,
    ) {}
}
