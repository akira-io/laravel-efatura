<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'name'          => ['required', ...FiscalRules::text(3, 150)],
            'accountNumber' => ['nullable', 'required_without:' . $field('nib'), 'prohibits:' . $field('nib'), 'regex:/\A[0-9]{1,15}\z/'],
            'nib'           => ['nullable', 'required_without:' . $field('accountNumber'), 'prohibits:' . $field('accountNumber'), 'regex:/\A[0-9]{21}\z/'],
        ];
    }
}
