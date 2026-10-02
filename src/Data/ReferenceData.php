<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ReferenceData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<TaxData> $taxes
     */
    public function __construct(
        public readonly ?FiscalDocumentData $fiscalDocument = null,
        public readonly ?string $innerDocumentNumber = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
        public readonly ?Money $paymentAmount = null,
        public readonly array $taxes = [],
    ) {
        $rules            = self::rules();
        $rules['taxes'][] = new DataInstances(TaxData::class);
        $this->validateFiscalFields($rules);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'fiscalDocument'      => ['required_without_all:' . $field('paymentAmount') . ',' . $field('taxes')],
            'innerDocumentNumber' => ['nullable', ...FiscalRules::code()],
            'paymentAmount'       => ['nullable', FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
            'taxes'               => ['array', 'list', 'max:2'],
        ];
    }
}
