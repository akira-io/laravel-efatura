<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Support\Fiscal;

final readonly class EmitterConfig
{
    public function __construct(
        public ?string $taxId,
        public ?string $name,
        public ?int $led,
        public AddressConfig $address,
        public ContactsConfig $contacts,
    ) {}

    /**
     * @return array{taxId: array{value: ?string, countryCode: string}, name: ?string, address: array<string, string>|null, contacts: array<string, ?string>}
     */
    public function partyPayload(): array
    {
        return [
            'taxId'    => $this->taxIdPayload(),
            'name'     => $this->name,
            'address'  => $this->address->addressPayload(),
            'contacts' => $this->contacts->contactsPayload(),
        ];
    }

    /**
     * @return array{value: ?string, countryCode: string}
     */
    public function taxIdPayload(): array
    {
        return ['value' => $this->taxId, 'countryCode' => Fiscal::COUNTRY];
    }
}
