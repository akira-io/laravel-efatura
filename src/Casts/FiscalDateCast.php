<?php

declare(strict_types=1);

namespace Akira\Efatura\Casts;

use Akira\Efatura\Rules\FiscalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final readonly class FiscalDateCast implements Cast
{
    public function __construct(private string $format = 'Y-m-d') {}

    /** @param array<string, mixed> $properties
     * @param CreationContext<Data> $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): CarbonImmutable
    {
        if (! \is_string($value)) {
            throw ValidationException::withMessages([$property->name => __('efatura::efatura.validation.fiscal_date')]);
        }

        Validator::make([$property->name => $value], [$property->name => ['required', 'string', new FiscalDate($this->format)]])->validate();

        $date = CarbonImmutable::createFromFormat('!' . $this->format, $value, 'Atlantic/Cape_Verde');
        if (! $date instanceof CarbonImmutable) {
            throw ValidationException::withMessages([$property->name => __('efatura::efatura.validation.fiscal_date')]);
        }

        return $date;
    }
}
