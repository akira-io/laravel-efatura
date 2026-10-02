<?php

declare(strict_types=1);

namespace Akira\Efatura\Casts;

use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class BigDecimalCast implements Cast
{
    public function __construct(private int $maxScale = Fiscal::AMOUNT_SCALE) {}

    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): BigDecimal
    {
        if (! DecimalFormatter::isPlainDecimal($value)) {
            throw ValidationException::withMessages([FieldPath::of($context, $property) => __('efatura::efatura.validation.invalid_decimal')]);
        }

        $decimal = BigDecimal::of($value);

        if (! DecimalFormatter::fitsScale($decimal, $this->maxScale)) {
            throw ValidationException::withMessages([FieldPath::of($context, $property) => __('efatura::efatura.validation.decimal_scale_exceeded')]);
        }

        return $decimal;
    }
}
