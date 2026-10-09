<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\ChronologicalOrder;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DurationData extends FiscalData
{
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $startDate,
        #[FiscalDateFormat(Fiscal::TIME_FORMAT)]
        public readonly CarbonImmutable $startTime,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $endDate = null,
        #[FiscalDateFormat(Fiscal::TIME_FORMAT)]
        public readonly ?CarbonImmutable $endTime = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'startDate' => [new FiscalDate],
            'startTime' => [new FiscalDate(Fiscal::TIME_FORMAT)],
            'endDate'   => ['required_with:' . FieldPath::of($context, 'endTime'), new FiscalDate],
            'endTime'   => ['required_with:' . FieldPath::of($context, 'endDate'), new FiscalDate(Fiscal::TIME_FORMAT), ChronologicalOrder::between(
                $context,
                ['startDate' => Fiscal::DATE_FORMAT, 'startTime' => Fiscal::TIME_FORMAT],
                ['endDate' => Fiscal::DATE_FORMAT, 'endTime' => Fiscal::TIME_FORMAT],
            )],
        ];
    }
}
