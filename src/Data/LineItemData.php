<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\ValidationPayload;
use Brick\Money\Money;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class LineItemData extends FiscalData
{
    /**
     * @param list<TaxData> $taxes
     */
    public function __construct(
        public readonly QuantityData $quantity,
        public readonly ItemData $item,
        #[MapName('lineTypeCode')]
        public readonly LineType $lineType = LineType::Normal,
        public readonly ?string $id = null,
        public readonly ?string $lineReferenceId = null,
        public readonly ?int $orderLineReference = null,
        #[CveAmount]
        public readonly ?Money $price = null,
        #[CveAmount]
        public readonly ?Money $priceExtension = null,
        public readonly ?DiscountData $discount = null,
        #[CveAmount]
        public readonly ?Money $netTotal = null,
        #[DataCollectionOf(TaxData::class), ListType, Max(2)]
        public readonly array $taxes = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $type = ValidationPayload::enum($context, 'lineTypeCode', LineType::class, LineType::Normal);

        return [
            'quantity.value'     => [FiscalNumber::positive()],
            'id'                 => FiscalRules::code(),
            'lineReferenceId'    => [Rule::requiredIf($type === LineType::Charge), ...FiscalRules::code()],
            'orderLineReference' => ['integer', 'between:1,99999'],
            'price'              => [FiscalNumber::amount(Fiscal::CURRENCY)],
            'priceExtension'     => [FiscalNumber::amount(Fiscal::CURRENCY)],
            'netTotal'           => [FiscalNumber::amount(Fiscal::CURRENCY)],
        ];
    }
}
