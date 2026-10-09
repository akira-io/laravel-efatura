<?php

declare(strict_types=1);

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PayeeFinancialAccountData;
use Akira\Efatura\Data\StandardIdentificationData;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Illuminate\Validation\ValidationException;

it('rejects two tax representations at their nested paths', function (): void {
    $payload = F::linePayload(['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxAmount' => '1']]]);

    expect(fn (): LineItemData => LineItemData::from($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe([
            'taxes.0.taxPercentage' => ['The taxes.0.tax percentage field prohibits taxes.0.tax amount / taxes.0.tax exemption reason code from being present.'],
            'taxes.0.taxAmount'     => ['The taxes.0.tax amount field prohibits taxes.0.tax percentage / taxes.0.tax exemption reason code from being present.'],
        ]);
    });
});

it('requires the tax representation chosen by the tax type', function (TaxType|string $type, array $tax, string $field, string $message): void {
    $payload = F::linePayload(['taxes' => [['taxTypeCode' => $type, ...$tax]]]);

    expect(fn (): LineItemData => LineItemData::from($payload))->toFailValidationOn('taxes.0.' . $field, $message);
})->with([
    'exemption for not applicable enum'  => [TaxType::NotApplicable, ['taxPercentage' => '15'], 'taxExemptionReasonCode', 'The taxes.0.tax exemption reason code field is required.'],
    'exemption for not applicable value' => ['NA', ['taxPercentage' => '15'], 'taxExemptionReasonCode', 'The taxes.0.tax exemption reason code field is required.'],
    'stamp code for stamp tax enum'      => [TaxType::StampTax, ['taxAmount' => '1'], 'stampTaxCode', 'The taxes.0.stamp tax code field is required.'],
    'stamp code for stamp tax value'     => ['IS', ['taxAmount' => '1'], 'stampTaxCode', 'The taxes.0.stamp tax code field is required.'],
]);

it('accepts a single standard identification', function (): void {
    expect(StandardIdentificationData::from(['upc' => '0123'])->upc)->toBe('0123');
});

it('accepts a single bank account choice', function (): void {
    expect(PayeeFinancialAccountData::from(['name' => 'Account Holder', 'accountNumber' => '12345'])->accountNumber)->toBe('12345');
});

it('reports every competing standard identification', function (): void {
    $payload = ['ean' => 'A', 'pharmacode' => 'B'];

    expect(fn (): StandardIdentificationData => StandardIdentificationData::from($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe([
            'ean'        => ['The ean field prohibits gtin / upc / pharmacode from being present.'],
            'pharmacode' => ['The pharmacode field prohibits gtin / ean / upc from being present.'],
        ]);
    });
});
