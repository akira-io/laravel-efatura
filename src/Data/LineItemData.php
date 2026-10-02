<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class LineItemData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<TaxData> $taxes
     */
    public function __construct(
        public readonly QuantityData $quantity,
        public readonly ItemData $item,
        public readonly LineType $lineTypeCode = LineType::Normal,
        public readonly ?string $id = null,
        public readonly ?string $lineReferenceId = null,
        public readonly ?int $orderLineReference = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $price = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $priceExtension = null,
        public readonly ?DiscountData $discount = null,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly ?Money $netTotal = null,
        public readonly array $taxes = [],
    ) {
        $rules            = self::rules();
        $rules['taxes'][] = new DataInstances(TaxData::class);
        $this->validateFiscalFields($rules);
        Validator::make(['quantity' => ['value' => $quantity->value]], ['quantity.value' => ['required', new FiscalNumber(positive: true)]])->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'id'                 => ['nullable', ...FiscalRules::code()],
            'lineReferenceId'    => ['nullable', 'required_if:lineTypeCode,C', ...FiscalRules::code()],
            'orderLineReference' => ['nullable', 'integer', 'between:1,99999'],
            'price'              => ['nullable', new FiscalNumber(currency: 'CVE')],
            'priceExtension'     => ['nullable', new FiscalNumber(currency: 'CVE')],
            'netTotal'           => ['nullable', new FiscalNumber(currency: 'CVE')],
            'taxes'              => ['array', 'list', 'max:2'],
        ];
    }
}
