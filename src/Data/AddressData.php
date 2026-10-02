<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\NotBlank;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddressData extends FiscalData
{
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
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context, Catalogs $catalogs): array
    {
        return [
            'countryCode'   => [new OfficialCode(Catalog::Countries, $catalogs)],
            'addressDetail' => FiscalRules::text(1, 100),
            'addressCode'   => [
                new NotBlank,
                'required_if:' . FieldPath::of($context, 'countryCode') . ',' . Fiscal::COUNTRY,
                'regex:/\ACV[0-9]{18}\z/',
                new OfficialCode(Catalog::Locations, $catalogs),
            ],
            ...collect(['state', 'city', 'region', 'street', 'streetDetail', 'buildingName', 'buildingNumber', 'buildingFloor', 'postalCode'])
                ->mapWithKeys(static fn (string $field): array => [$field => FiscalRules::text(1, 100)])->all(),
        ];
    }
}
