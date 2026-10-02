<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Money\Money;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class ForeignMoneyCast implements Cast
{
    /** @param array<string, mixed> $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money
    {
        $currency = $properties['currencyCode'] ?? null;
        if (! \is_string($currency)) {
            throw new EfaturaValidationException('currencyCode', __('efatura::efatura.validation.invalid_currency'));
        }

        return new MoneyCast($currency, 5, false)->cast($property, $value, $properties, $context);
    }
}
