<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class EmissionContextData extends FiscalData
{
    public function __construct(
        public readonly EmissionMode $issueMode = EmissionMode::Online,
        public readonly ?ContingencyData $contingency = null,
        public readonly ?TaxIdData $transmitterTaxId = null,
        public readonly ?SoftwareData $software = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $mode  = ValidationPayload::enum($context, 'issueMode', EmissionMode::class, EmissionMode::Online);
        $rules = [
            'contingency'                  => [Rule::requiredIf($mode !== EmissionMode::Online), Rule::prohibitedIf($mode === EmissionMode::Online)],
            'transmitterTaxId.countryCode' => ['in:' . Fiscal::COUNTRY],
        ];

        if (! $mode instanceof EmissionMode || ! \is_array(ValidationPayload::value($context, 'contingency'))) {
            return $rules;
        }

        return [
            ...$rules,
            'contingency.iuc'            => [Rule::requiredIf($mode === EmissionMode::Off)],
            'contingency.issueTime'      => [Rule::requiredIf($mode === EmissionMode::Offline)],
            'contingency.reasonTypeCode' => [Rule::enum(ContingencyReason::class)->only(ContingencyReason::allowedFor($mode))],
        ];
    }
}
