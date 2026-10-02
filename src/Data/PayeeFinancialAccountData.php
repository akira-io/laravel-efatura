<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class PayeeFinancialAccountData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $name,
        public readonly ?string $accountNumber = null,
        public readonly ?string $nib = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name'          => ['required', ...FiscalRules::text(3, 150)],
            'accountNumber' => ['nullable', 'required_without:nib', 'prohibits:nib', 'regex:/\A[0-9]{1,15}\z/'],
            'nib'           => ['nullable', 'required_without:accountNumber', 'prohibits:accountNumber', 'regex:/\A[0-9]{21}\z/'],
        ];
    }
}
