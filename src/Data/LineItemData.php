<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Rules\DataInstances;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
        #[CveAmount]
        public readonly ?Money $price = null,
        #[CveAmount]
        public readonly ?Money $priceExtension = null,
        public readonly ?DiscountData $discount = null,
        #[CveAmount]
        public readonly ?Money $netTotal = null,
        public readonly array $taxes = [],
    ) {
        $rules            = self::rules();
        $rules['taxes'][] = new DataInstances(TaxData::class);
        $this->validateFiscalFields($rules);
        Validator::make(['quantity' => ['value' => $quantity->value]], ['quantity.value' => ['required', FiscalNumber::positive()]])->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'id'                 => ['nullable', ...FiscalRules::code()],
            'lineReferenceId'    => ['nullable', 'required_if:' . $field('lineTypeCode') . ',C', ...FiscalRules::code()],
            'orderLineReference' => ['nullable', 'integer', 'between:1,99999'],
            'price'              => ['nullable', FiscalNumber::amount(Fiscal::CURRENCY)],
            'priceExtension'     => ['nullable', FiscalNumber::amount(Fiscal::CURRENCY)],
            'netTotal'           => ['nullable', FiscalNumber::amount(Fiscal::CURRENCY)],
            'taxes'              => ['array', 'list', 'max:2'],
        ];
    }
}
