<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FiscalRules;

final class ContactsData extends FiscalData
{
    public function __construct(
        public readonly ?string $telephone = null,
        public readonly ?string $mobilephone = null,
        public readonly ?string $telefax = null,
        public readonly ?string $email = null,
        public readonly ?string $website = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'telephone'   => FiscalRules::phone(),
            'mobilephone' => FiscalRules::phone(),
            'telefax'     => FiscalRules::phone(),
            'email'       => FiscalRules::email(),
            'website'     => FiscalRules::website(),
        ];
    }
}
