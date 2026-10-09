<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Rules\NotBlank;
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
            'email'       => [new NotBlank, 'max:256', 'regex:/\A\w+(?:[-._]\w+)*@\w+(?:[-._]\w+)*\.\w+(?:\.\w+)*\z/u'],
            'website'     => FiscalRules::website(),
        ];
    }
}
