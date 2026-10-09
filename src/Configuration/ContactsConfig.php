<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class ContactsConfig
{
    public function __construct(
        public ?string $email,
        public ?string $telephone,
        public ?string $mobile,
        public ?string $telefax = null,
        public ?string $website = null,
    ) {}

    /**
     * @return array{email: ?string, telephone: ?string, mobilephone: ?string, telefax: ?string, website: ?string}
     */
    public function contactsPayload(): array
    {
        return [
            'email'       => $this->email,
            'telephone'   => $this->telephone,
            'mobilephone' => $this->mobile,
            'telefax'     => $this->telefax,
            'website'     => $this->website,
        ];
    }
}
