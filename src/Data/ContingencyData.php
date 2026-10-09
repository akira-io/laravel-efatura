<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Rules\NotBlank;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\ValidationPayload;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ContingencyData extends FiscalData
{
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT, instant: true)]
        public readonly CarbonImmutable $issueDate,
        #[MapName('reasonTypeCode')]
        public readonly ContingencyReason $reason,
        public readonly int $ledCode,
        public readonly ?string $iuc = null,
        #[FiscalDateFormat(Fiscal::TIME_FORMAT, instant: true)]
        public readonly ?CarbonImmutable $issueTime = null,
        public readonly ?string $reasonDescription = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $reason = ValidationPayload::enum($context, 'reasonTypeCode', ContingencyReason::class);

        return [
            'issueDate'         => [new FiscalDate(instant: true)],
            'ledCode'           => FiscalRules::ledCode(),
            'iuc'               => [new NotBlank, 'regex:/\A[0-9]{4}\/[0-9]+\z/'],
            'issueTime'         => [new FiscalDate(Fiscal::TIME_FORMAT, instant: true)],
            'reasonDescription' => [Rule::requiredIf($reason === ContingencyReason::Other), ...FiscalRules::text(10, 500)],
        ];
    }
}
