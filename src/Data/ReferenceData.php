<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ReferenceData extends FiscalData
{
    /**
     * @param list<TaxData> $taxes
     */
    public function __construct(
        public readonly ?FiscalDocumentData $fiscalDocument = null,
        public readonly ?string $innerDocumentNumber = null,
        #[CveAmount]
        public readonly ?Money $paymentAmount = null,
        #[DataCollectionOf(TaxData::class), ListType, Max(2)]
        public readonly array $taxes = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'fiscalDocument'      => ['required_without_all:' . FieldPath::list($context, 'paymentAmount', 'taxes')],
            'innerDocumentNumber' => FiscalRules::code(),
            'paymentAmount'       => [FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
        ];
    }
}
