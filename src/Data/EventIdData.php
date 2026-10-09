<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;

final class EventIdData extends FiscalData
{
    public function __construct(
        #[MapName('repositoryCode')]
        public readonly Environment $repository,
        #[FiscalDateFormat(Fiscal::DATE_TIME_FORMAT, instant: true)]
        public readonly CarbonImmutable $issueDateTime,
        public readonly string $taxId,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDateTime' => [new FiscalDate(Fiscal::DATE_TIME_FORMAT, instant: true), 'before:' . Fiscal::IDENTIFIER_DATE_LIMIT],
            'taxId'         => ['regex:/\A' . FiscalRules::CV_TAX_ID . '\z/'],
        ];
    }
}
