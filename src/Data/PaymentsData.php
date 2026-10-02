<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentsData extends FiscalData
{
    /**
     * @param list<PayeeFinancialAccountData> $payeeFinancialAccounts
     * @param list<PaymentData>               $payments
     */
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $paymentDueDate = null,
        public readonly ?PaymentTermsData $paymentTerms = null,
        #[DataCollectionOf(PayeeFinancialAccountData::class), ListType]
        public readonly array $payeeFinancialAccounts = [],
        #[DataCollectionOf(PaymentData::class), ListType, Prohibits('paymentDueDate', 'paymentTerms', 'payeeFinancialAccounts')]
        public readonly array $payments = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'paymentDueDate' => [new FiscalDate],
        ];
    }
}
