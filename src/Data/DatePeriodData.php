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

final class DatePeriodData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $startDate,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $endDate,
    ) {
        $this->validateFiscalFields(self::rules());
        Validator::make([
            'startDate' => $startDate->format(Fiscal::DATE_FORMAT),
            'endDate'   => $endDate->format(Fiscal::DATE_FORMAT),
        ], ['endDate' => ['after_or_equal:startDate']])->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['startDate' => ['required', new FiscalDate], 'endDate' => ['required', new FiscalDate]];
    }
}
