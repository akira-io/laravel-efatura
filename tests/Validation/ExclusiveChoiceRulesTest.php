<?php

declare(strict_types=1);

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PayeeFinancialAccountData;
use Akira\Efatura\Data\StandardIdentificationData;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Illuminate\Validation\ValidationException;

function choiceErrorsOf(Closure $creation): array
{
    try {
        $creation();
    } catch (ValidationException $validationException) {
        return array_keys($validationException->errors());
    }

    test()->fail('Expected a ValidationException.');
}

it('rejects two tax representations at their nested paths', function (): void {
    $errors = choiceErrorsOf(fn (): LineItemData => LineItemData::from(F::linePayload(['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxAmount' => '1']]])));

    expect($errors)->toContain('taxes.0.taxPercentage', 'taxes.0.taxAmount');
});

it('requires the tax representation chosen by the tax type', function (TaxType|string $type, array $tax, string $field): void {
    $errors = choiceErrorsOf(fn (): LineItemData => LineItemData::from(F::linePayload(['taxes' => [['taxTypeCode' => $type, ...$tax]]])));

    expect($errors)->toContain('taxes.0.' . $field);
})->with([
    'exemption for not applicable enum'  => [TaxType::NotApplicable, ['taxPercentage' => '15'], 'taxExemptionReasonCode'],
    'exemption for not applicable value' => ['NA', ['taxPercentage' => '15'], 'taxExemptionReasonCode'],
    'stamp code for stamp tax enum'      => [TaxType::StampTax, ['taxAmount' => '1'], 'stampTaxCode'],
    'stamp code for stamp tax value'     => ['IS', ['taxAmount' => '1'], 'stampTaxCode'],
]);

it('accepts exactly one standard identification or bank account choice', function (): void {
    expect(StandardIdentificationData::from(['upc' => '0123'])->upc)->toBe('0123')
        ->and(PayeeFinancialAccountData::from(['name' => 'Account Holder', 'accountNumber' => '12345'])->accountNumber)->toBe('12345');
});

it('reports every competing standard identification', function (): void {
    expect(choiceErrorsOf(fn (): StandardIdentificationData => StandardIdentificationData::from(['ean' => 'A', 'pharmacode' => 'B'])))
        ->toContain('ean', 'pharmacode');
});
