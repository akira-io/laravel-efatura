<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\NotBlank;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

final class PaymentData extends FiscalData
{
    public function __construct(
        public readonly ?string $paymentMeansCode = null,
        public readonly ?string $paymentReference = null,
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly ?CarbonImmutable $paymentDate = null,
        #[CveAmount]
        public readonly ?Money $paymentAmount = null,
        public readonly ?PayeeFinancialAccountData $payeeFinancialAccount = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Catalogs $catalogs): array
    {
        return [
            'paymentMeansCode' => [new NotBlank, new OfficialCode(Catalog::PaymentMeans, $catalogs)],
            'paymentReference' => FiscalRules::code(),
            'paymentDate'      => [new FiscalDate],
            'paymentAmount'    => [FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
        ];
    }
}
