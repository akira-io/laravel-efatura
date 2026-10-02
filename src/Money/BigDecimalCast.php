<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Math\BigDecimal;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class BigDecimalCast implements Cast
{
    public function __construct(private int $maxScale = 5) {}

    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): BigDecimal
    {
        if (! \is_int($value) && ! \is_string($value) && ! $value instanceof BigDecimal) {
            throw new EfaturaValidationException($property->name, __('efatura.validation.invalid_decimal'));
        }

        return DecimalFormatter::parse($value, $this->maxScale);
    }
}
