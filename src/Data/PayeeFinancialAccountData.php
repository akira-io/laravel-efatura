<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Rules\NotBlank;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PayeeFinancialAccountData extends FiscalData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $accountNumber = null,
        public readonly ?string $nib = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'name'          => FiscalRules::text(3, 150),
            'accountNumber' => [new NotBlank, 'required_without:' . FieldPath::of($context, 'nib'), 'prohibits:' . FieldPath::of($context, 'nib'), 'regex:/\A[0-9]{1,15}\z/'],
            'nib'           => [new NotBlank, 'required_without:' . FieldPath::of($context, 'accountNumber'), 'prohibits:' . FieldPath::of($context, 'accountNumber'), 'regex:/\A[0-9]{21}\z/'],
        ];
    }
}
