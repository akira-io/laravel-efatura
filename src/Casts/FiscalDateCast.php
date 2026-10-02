<?php

declare(strict_types=1);

namespace Akira\Efatura\Casts;

use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Transformers\Transformer;

final readonly class FiscalDateCast implements Cast, Transformer
{
    public function __construct(private string $format = Fiscal::DATE_FORMAT) {}

    /**
     * @param array<string, mixed>  $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): CarbonImmutable
    {
        $date = \is_string($value) ? $this->parse($value) : null;

        if (! $date instanceof CarbonImmutable) {
            $path = FieldPath::of($context, $property);

            throw ValidationException::withMessages([
                $path => __('efatura::efatura.validation.fiscal_date', ['attribute' => str_replace('_', ' ', Str::snake($path))]),
            ]);
        }

        return $date;
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return new DateTimeInterfaceTransformer($this->format, '')->transform($property, $value, $context);
    }

    private function parse(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!' . $this->format, $value, Fiscal::TIMEZONE);
        } catch (InvalidFormatException) {
            return null;
        }

        if (! $date instanceof CarbonImmutable || $date->format($this->format) !== $value) {
            return null;
        }

        if ($this->format === Fiscal::DATE_FORMAT && $value < Fiscal::EARLIEST_DATE) {
            return null;
        }

        return $date;
    }
}
