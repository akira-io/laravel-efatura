<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DurationData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $startDate,
        #[FiscalDateFormat(Fiscal::TIME_FORMAT)]
        public readonly CarbonImmutable $startTime,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $endDate = null,
        #[FiscalDateFormat(Fiscal::TIME_FORMAT)]
        public readonly ?CarbonImmutable $endTime = null,
    ) {
        $this->validateFiscalFields(self::rules());
        if ($endDate instanceof CarbonImmutable && $endTime instanceof CarbonImmutable) {
            Validator::make([
                'start' => $startDate->format(Fiscal::DATE_FORMAT) . 'T' . $startTime->format(Fiscal::TIME_FORMAT),
                'end'   => $endDate->format(Fiscal::DATE_FORMAT) . 'T' . $endTime->format(Fiscal::TIME_FORMAT),
            ], ['end' => ['after_or_equal:start']])->validate();
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'startDate' => ['required', new FiscalDate],
            'startTime' => ['required', new FiscalDate(Fiscal::TIME_FORMAT)],
            'endDate'   => ['nullable', 'required_with:' . $field('endTime'), new FiscalDate],
            'endTime'   => ['nullable', 'required_with:' . $field('endDate'), new FiscalDate(Fiscal::TIME_FORMAT)],
        ];
    }
}
