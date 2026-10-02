<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddressData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $countryCode,
        public readonly string $addressDetail,
        public readonly ?string $state = null,
        public readonly ?string $city = null,
        public readonly ?string $region = null,
        public readonly ?string $street = null,
        public readonly ?string $streetDetail = null,
        public readonly ?string $buildingName = null,
        public readonly ?string $buildingNumber = null,
        public readonly ?string $buildingFloor = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $addressCode = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'countryCode'   => ['required', new OfficialCode('countries')],
            'addressDetail' => ['required', ...FiscalRules::text(1, 100)],
            'addressCode'   => ['nullable', 'required_if:' . $field('countryCode') . ',CV', 'regex:/\ACV[0-9]{18}\z/', new OfficialCode('locations')],
            ...collect(['state', 'city', 'region', 'street', 'streetDetail', 'buildingName', 'buildingNumber', 'buildingFloor', 'postalCode'])
                ->mapWithKeys(static fn (string $field): array => [$field => ['nullable', ...FiscalRules::text(1, 100)]])->all(),
        ];
    }
}
