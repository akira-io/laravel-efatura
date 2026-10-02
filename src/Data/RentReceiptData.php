<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\ContractType;
use Akira\Efatura\Enums\RentPurpose;
use Akira\Efatura\Enums\RentType;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Attributes\MapName;

final class RentReceiptData extends FiscalData
{
    public function __construct(
        public readonly string $assetId,
        #[MapName('rentPurposeTypeCode')]
        public readonly RentPurpose $rentPurpose,
        #[MapName('contractTypeCode')]
        public readonly ContractType $contractType,
        #[MapName('rentTypeCode')]
        public readonly RentType $rentType,
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
