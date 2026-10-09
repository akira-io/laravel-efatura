<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;

final class IudData extends FiscalData
{
    public function __construct(
        #[MapName('repositoryCode')]
        public readonly Environment $repository,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT, instant: true)]
        public readonly CarbonImmutable $issueDate,
        public readonly string $emitterTaxId,
        public readonly int $ledCode,
        #[MapName('documentTypeCode')]
        public readonly DocumentType $documentType,
        public readonly int $documentNumber,
        public readonly ?string $randomCode = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDate'      => [new FiscalDate(instant: true), 'before:' . Fiscal::IDENTIFIER_DATE_LIMIT],
            'emitterTaxId'   => ['regex:/\A' . FiscalRules::CV_TAX_ID . '\z/'],
            'ledCode'        => FiscalRules::ledCode(),
            'documentNumber' => FiscalRules::documentNumber(),
            'randomCode'     => ['regex:/\A[0-9]{10}\z/'],
        ];
    }
}
