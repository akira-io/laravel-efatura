<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Enums\DiscountValueType;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class DiscountValueCast implements Cast
{
    /** @param array<string, mixed> $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money|BigDecimal
    {
        $type = $properties['valueType'] ?? DiscountValueType::Percentage;

        return $type === DiscountValueType::Amount || $type === 'A'
            ? new MoneyCast('CVE', 5, false)->cast($property, $value, $properties, $context)
            : (new BigDecimalCast)->cast($property, $value, $properties, $context);
    }
}
