<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\CveAmount;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Money\Money;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;

final class SelfBillingAuthorizationRequestData extends FiscalData
{
    public function __construct(
        public readonly string $taxId,
        #[MapName('documentTypeCode')]
        public readonly DocumentType $documentType,
        public readonly string $mobilePhoneNumber,
        #[CveAmount]
        public readonly Money $totalAmount,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $selfBillable = array_filter(DocumentType::cases(), static fn (DocumentType $type): bool => $type->isSelfBillable());

        return [
            'taxId'             => FiscalRules::cvTaxId(),
            'documentTypeCode'  => [Rule::in(array_map(static fn (DocumentType $type): string => $type->value, $selfBillable))],
            'mobilePhoneNumber' => FiscalRules::phone(),
            'totalAmount'       => [FiscalNumber::positiveAmount(Fiscal::CURRENCY)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['documentTypeCode.in' => __('efatura::efatura.validation.self_billing_document_type')];
    }
}
