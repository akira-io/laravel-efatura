<?php

declare(strict_types=1);

namespace Akira\Efatura\Transformers;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class BigDecimalTransformer implements Transformer
{
    public function __construct(private int $maxScale = Fiscal::AMOUNT_SCALE) {}

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        $field = FieldPath::name($property);

        if (! $value instanceof BigDecimal) {
            throw ValidationException::withMessages([$field => __('efatura::efatura.validation.invalid_decimal')]);
        }

        try {
            return DecimalFormatter::decimal($value, $this->maxScale, $field);
        } catch (EfaturaValidationException $efaturaValidationException) {
            throw ValidationException::withMessages([$field => $efaturaValidationException->getMessage()]);
        }
    }
}
