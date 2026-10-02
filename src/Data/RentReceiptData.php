<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\ContractType;
use Akira\Efatura\Enums\RentPurpose;
use Akira\Efatura\Enums\RentType;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class RentReceiptData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $assetId,
        public readonly RentPurpose $rentPurposeTypeCode,
        public readonly ContractType $contractTypeCode,
        public readonly RentType $rentTypeCode,
        public readonly string $referencePeriod,
        public readonly AddressData $address,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'assetId'         => ['required', ...FiscalRules::code()],
            'referencePeriod' => ['required', 'regex:/\A2[0-9]{3}-(?:0[1-9]|1[012])\z/'],
        ];
    }
}
