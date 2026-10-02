<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Illuminate\Support\Arr;

final readonly class AddressConfig
{
    public function __construct(
        public ?string $countryCode,
        public ?string $region,
        public ?string $city,
        public ?string $street,
        public ?string $postalCode,
        public ?string $addressDetail = null,
        public ?string $addressCode = null,
        public ?string $state = null,
        public ?string $streetDetail = null,
        public ?string $buildingName = null,
        public ?string $buildingNumber = null,
        public ?string $buildingFloor = null,
    ) {}

    /**
     * @return array<string, string>|null
     */
    public function addressPayload(): ?array
    {
        $address = Arr::whereNotNull([
            'countryCode'    => $this->countryCode,
            'addressDetail'  => $this->addressDetail,
            'addressCode'    => $this->addressCode,
            'state'          => $this->state,
            'region'         => $this->region,
            'city'           => $this->city,
            'street'         => $this->street,
            'streetDetail'   => $this->streetDetail,
            'buildingName'   => $this->buildingName,
            'buildingNumber' => $this->buildingNumber,
            'buildingFloor'  => $this->buildingFloor,
            'postalCode'     => $this->postalCode,
        ]);

        return $address === [] ? null : $address;
    }
}
