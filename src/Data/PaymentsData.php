<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentsData extends Data
{
    use ValidatesFiscalFields;

    /** @param list<PayeeFinancialAccountData> $payeeFinancialAccounts
     * @param list<PaymentData> $payments
     */
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $paymentDueDate = null,
        public readonly ?PaymentTermsData $paymentTerms = null,
        public readonly array $payeeFinancialAccounts = [],
        public readonly array $payments = [],
    ) {
        $rules                             = self::rules();
        $rules['payeeFinancialAccounts'][] = new DataInstances(PayeeFinancialAccountData::class);
        $rules['payments'][]               = new DataInstances(PaymentData::class);
        $this->validateFiscalFields($rules);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'paymentDueDate'         => ['nullable', new FiscalDate],
            'payeeFinancialAccounts' => ['array', 'list'],
            'payments'               => ['array', 'list', 'prohibits:' . $field('paymentDueDate') . ',' . $field('paymentTerms') . ',' . $field('payeeFinancialAccounts')],
        ];
    }
}
