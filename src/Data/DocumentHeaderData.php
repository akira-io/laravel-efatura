<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;

final class DocumentHeaderData extends FiscalData
{
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT, instant: true)]
        public readonly CarbonImmutable $issueDate,
        #[FiscalDateFormat(Fiscal::TIME_FORMAT, instant: true)]
        public readonly CarbonImmutable $issueTime,
        public readonly int $ledCode,
        #[MapName('serie')]
        public readonly ?string $series = null,
        public readonly ?int $documentNumber = null,
        public readonly ?string $innerDocumentNumber = null,
        public readonly ?bool $isIsolatedAct = null,
        public readonly ?SelfBillingData $selfBilling = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDate'           => [new FiscalDate(instant: true)],
            'issueTime'           => [new FiscalDate(Fiscal::TIME_FORMAT, instant: true)],
            'ledCode'             => FiscalRules::ledCode(),
            'serie'               => FiscalRules::series(),
            'documentNumber'      => FiscalRules::documentNumber(),
            'innerDocumentNumber' => FiscalRules::code(),
        ];
    }
}
