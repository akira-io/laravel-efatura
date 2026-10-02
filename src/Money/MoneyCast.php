<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Support\FieldPath;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class MoneyCast implements Cast, Transformer
{
    public function __construct(
        private string $currency,
        private int $scale = 2,
        private bool $round = true,
    ) {
        if ($this->round && $this->scale !== 2) {
            throw DefinitionException::roundingScale($this->scale);
        }
    }

    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money
    {
        $path = FieldPath::of($context, $property);

        if (! \is_int($value) && ! \is_string($value) && ! $value instanceof Money) {
            throw ValidationException::withMessages([$path => __('efatura::efatura.validation.invalid_money_input')]);
        }

        try {
            return $this->round
                ? FiscalMoney::of($value, $this->currency, $path)
                : FiscalMoney::exact($value, $this->currency, $this->scale, $path);
        } catch (EfaturaValidationException $efaturaValidationException) {
            throw ValidationException::withMessages([$path => $efaturaValidationException->getMessage()]);
        }
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return new MoneyTransformer($this->scale, $this->round)->transform($property, $value, $context);
    }
}
