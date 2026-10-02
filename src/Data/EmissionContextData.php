<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class EmissionContextData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly EmissionMode $issueMode = EmissionMode::Online,
        public readonly ?ContingencyData $contingency = null,
        public readonly ?TaxIdData $transmitterTaxId = null,
        public readonly ?SoftwareData $software = null,
    ) {
        $this->validateFiscalFields(self::rules());
        $this->validateFiscalFields(['contingency' => [
            Rule::requiredIf($issueMode !== EmissionMode::Online),
            Rule::prohibitedIf($issueMode === EmissionMode::Online),
        ]]);
        if ($transmitterTaxId instanceof TaxIdData) {
            Validator::make(['countryCode' => $transmitterTaxId->countryCode], ['countryCode' => ['required', 'in:' . Fiscal::COUNTRY]])->validate();
        }

        if ($contingency instanceof ContingencyData) {
            Validator::make([
                'iuc'            => $contingency->iuc, 'issueTime' => $contingency->issueTime,
                'reasonTypeCode' => $contingency->reasonTypeCode->value,
            ], [
                'iuc'            => [Rule::requiredIf($issueMode === EmissionMode::Off)],
                'issueTime'      => [Rule::requiredIf($issueMode === EmissionMode::Offline)],
                'reasonTypeCode' => [Rule::in($issueMode === EmissionMode::Offline ? ['0', '1', '4', '5'] : ['0', '2', '3'])],
            ])->validate();
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'contingency'      => ['nullable'],
            'transmitterTaxId' => ['nullable'], 'software' => ['nullable'],
        ];
    }
}
