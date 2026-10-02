<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class PaymentData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly ?string $paymentMeansCode = null,
        public readonly ?string $paymentReference = null,
        #[WithCast(FiscalDateCast::class, Fiscal::DATE_FORMAT)]
        #[WithTransformer(DateTimeInterfaceTransformer::class, Fiscal::DATE_FORMAT, '')]
        public readonly ?CarbonImmutable $paymentDate = null,
        #[WithCast(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false)]
        #[WithTransformer(MoneyTransformer::class, Fiscal::AMOUNT_SCALE, false)]
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
            'paymentMeansCode' => ['nullable', new OfficialCode('payment_means')],
            'paymentReference' => ['nullable', ...FiscalRules::code()],
            'paymentDate'      => ['nullable', new FiscalDate],
            'paymentAmount'    => ['nullable', new FiscalNumber(positive: true, currency: Fiscal::CURRENCY)],
        ];
    }
}
