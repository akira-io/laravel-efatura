<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class ItemData extends Data
{
    use ValidatesFiscalFields;

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
        #[DataCollectionOf(ExtraPropertyData::class)]
        public readonly array $extraProperties = [],
    ) {
        $this->validateFiscalFields(self::rules());
        Validator::make(['packQuantity' => ['value' => $packQuantity?->value]], ['packQuantity.value' => ['nullable', FiscalNumber::positive()]])->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'description'           => ['required', ...FiscalRules::text(1, 300)],
            'emitterIdentification' => ['required', ...FiscalRules::code()],
            'name'                  => ['nullable', ...FiscalRules::text(3, 150)],
            'brandName'             => ['nullable', ...FiscalRules::text(3, 150)],
            'modelName'             => ['nullable', ...FiscalRules::text(3, 150)],
            'extraProperties'       => ['array', 'list'],
        ];
    }
}
