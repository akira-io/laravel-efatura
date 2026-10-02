<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\ChronologicalOrder;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DatePeriodData extends FiscalData
{
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $startDate,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $endDate,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'startDate' => [new FiscalDate],
            'endDate'   => [new FiscalDate, ChronologicalOrder::between($context, ['startDate' => Fiscal::DATE_FORMAT], ['endDate' => Fiscal::DATE_FORMAT])],
        ];
    }
}
