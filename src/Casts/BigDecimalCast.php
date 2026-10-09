<?php

declare(strict_types=1);

namespace Akira\Efatura\Casts;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transformers\BigDecimalTransformer;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class BigDecimalCast implements Cast, Transformer
{
    public function __construct(private int $maxScale = Fiscal::AMOUNT_SCALE) {}

    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): BigDecimal
    {
        $path = FieldPath::of($context, $property);

        try {
            return DecimalFormatter::parse($value, $this->maxScale, $path);
        } catch (EfaturaValidationException $efaturaValidationException) {
            throw ValidationException::withMessages([$path => $efaturaValidationException->getMessage()]);
        }
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return new BigDecimalTransformer($this->maxScale)->transform($property, $value, $context);
    }
}
