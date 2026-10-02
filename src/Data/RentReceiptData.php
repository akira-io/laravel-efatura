<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\ContractType;
use Akira\Efatura\Enums\RentPurpose;
use Akira\Efatura\Enums\RentType;
use Akira\Efatura\Support\FiscalRules;

final class RentReceiptData extends FiscalData
{
    public function __construct(
        public readonly string $assetId,
        public readonly RentPurpose $rentPurposeTypeCode,
        public readonly ContractType $contractTypeCode,
        public readonly RentType $rentTypeCode,
        public readonly string $referencePeriod,
        public readonly AddressData $address,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'assetId'         => FiscalRules::code(),
            'referencePeriod' => ['regex:/\A2[0-9]{3}-(?:0[1-9]|1[012])\z/'],
        ];
    }
}
