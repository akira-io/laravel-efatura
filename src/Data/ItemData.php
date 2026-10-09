<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;

final class ItemData extends FiscalData
{
    /**
     * @param list<ExtraPropertyData> $extraProperties
     */
    public function __construct(
        public readonly string $description,
        public readonly string $emitterIdentification,
        public readonly ?QuantityData $packQuantity = null,
        public readonly ?string $name = null,
        public readonly ?string $brandName = null,
        public readonly ?string $modelName = null,
        public readonly ?StandardIdentificationData $standardIdentification = null,
        public readonly ?bool $hazardousRiskIndicator = null,
        #[DataCollectionOf(ExtraPropertyData::class), ListType, Max(Fiscal::MAX_LIST_ENTRIES)]
        public readonly array $extraProperties = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'description'           => FiscalRules::text(1, 300),
            'emitterIdentification' => FiscalRules::code(),
            'name'                  => FiscalRules::text(3, 150),
            'brandName'             => FiscalRules::text(3, 150),
            'modelName'             => FiscalRules::text(3, 150),
            'packQuantity.value'    => [FiscalNumber::positive()],
        ];
    }
}
