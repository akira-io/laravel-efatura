<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Akira\Efatura\Transformers\BigDecimalTransformer;
use Akira\Efatura\Transformers\MoneyTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class MappedFiscalValuesData extends Data
{
    public function __construct(
        #[MapName('wireRate')]
        #[WithTransformer(BigDecimalTransformer::class)]
        public readonly BigDecimal $rate,
        #[MapName('wireAmount')]
        #[WithTransformer(MoneyTransformer::class, 2, false)]
        public readonly Money $amount,
    ) {}
}
