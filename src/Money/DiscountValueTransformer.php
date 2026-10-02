<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Support\Fiscal;
use Brick\Money\Money;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class DiscountValueTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return $value instanceof Money
            ? new MoneyTransformer(Fiscal::AMOUNT_SCALE, false)->transform($property, $value, $context)
            : (new BigDecimalTransformer)->transform($property, $value, $context);
    }
}
