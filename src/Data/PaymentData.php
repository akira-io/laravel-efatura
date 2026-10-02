<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class PaymentData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly ?string $paymentMeansCode = null,
        public readonly ?string $paymentReference = null,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $paymentDate = null,
        #[CveAmount]
        public readonly ?Money $paymentAmount = null,
        public readonly ?PayeeFinancialAccountData $payeeFinancialAccount = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'paymentMeansCode' => ['nullable', new OfficialCode(Catalog::PaymentMeans)],
            'paymentReference' => ['nullable', ...FiscalRules::code()],
            'paymentDate'      => ['nullable', new FiscalDate],
            'paymentAmount'    => ['nullable', FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
        ];
    }
}
