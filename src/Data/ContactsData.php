<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class ContactsData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly ?string $telephone = null,
        public readonly ?string $mobilephone = null,
        public readonly ?string $telefax = null,
        public readonly ?string $email = null,
        public readonly ?string $website = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    public function validateEmitter(): void
    {
        $this->validateFiscalFields(['email' => ['required'], 'telephone' => ['required_without:mobilephone']]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'telephone'   => ['nullable', ...FiscalRules::phone()],
            'mobilephone' => ['nullable', ...FiscalRules::phone()],
            'telefax'     => ['nullable', ...FiscalRules::phone()],
            'email'       => ['nullable', 'max:256', 'regex:/\A\w+(?:[-._]\w+)*@\w+(?:[-._]\w+)*\.\w+(?:\.\w+)*\z/u'],
            'website'     => ['nullable', ...FiscalRules::website()],
        ];
    }
}
