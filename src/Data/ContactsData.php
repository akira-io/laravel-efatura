<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
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
            'telephone'   => ['nullable', 'regex:/\A[0-9]{7,20}\z/'],
            'mobilephone' => ['nullable', 'regex:/\A[0-9]{7,20}\z/'],
            'telefax'     => ['nullable', 'regex:/\A[0-9]{7,20}\z/'],
            'email'       => ['nullable', 'max:256', 'regex:/\A\w+(?:[-._]\w+)*@\w+(?:[-._]\w+)*\.\w+(?:\.\w+)*\z/u'],
            'website'     => ['nullable', 'max:256', 'regex:~\A(?:https?://)?[aA-zZ0-9_-]+(?:\.[aA-zZ0-9_-]+)*(?::[0-9]+)?(?:/[-._aA-zZ0-9]+)*(?:\?(?:[aA-zZ0-9_-]+=[+%aA-zZ0-9_-]*)(?:&[aA-zZ0-9_-]+=[+%aA-zZ0-9_-]*)*)?(?:\#[^\s]*)?\z~u'],
        ];
    }
}
