<?php

declare(strict_types=1);

use Akira\Efatura\Actions\VerifyDocumentTotalsAction;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\TotalsFixtures as T;
use Brick\Math\RoundingMode;

it('rounds fiscal amounts half up', function (): void {
    expect(DecimalFormatter::fiscalRounding())->toBe(RoundingMode::HalfUp);
});

it('accepts totals that reconcile with the line evidence', function (Closure $scenario): void {
    [$lines, $totals] = $scenario();

    expect(resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totals))->toBeNull();
})->with([
    'informational tax kept while informational net is excluded' => fn (): array => [T::informationalLines(), T::informationalTaxTotals()],
    'tax and withholding rounded down'                           => fn (): array => [T::taxAndWithholdingLines(), T::taxAndWithholdingTotals('0.01')],
    'tax and withholding rounded up'                             => fn (): array => [T::taxAndWithholdingLines(), T::taxAndWithholdingTotals('0.02')],
    'percentage discounts charges and rounding residual'         => fn (): array => [T::percentageDiscountAndChargeLines(), T::percentageDiscountAndChargeTotals()],
    'submitted net totals as amount discount allocation'         => fn (): array => [[F::line(['netTotal' => '93']), F::line(['netTotal' => '97'])], T::amountDiscountAllocationTotals()],
    'amount discount beside deduction and informational nets'    => fn (): array => [T::informationalLines('90'), T::amountDiscountWithInformationalTotals()],
    'exact final tax sum'                                        => fn (): array => [T::smallTaxLines(), T::smallTaxTotals('0.014', '0.154')],
    'final tax sum rounded down'                                 => fn (): array => [T::smallTaxLines(), T::smallTaxTotals('0.01', '0.15')],
    'final tax sum rounded up'                                   => fn (): array => [T::smallTaxLines(), T::smallTaxTotals('0.02', '0.16')],
]);

it('subtracts withholding from the payable amount', function (): void {
    $line   = F::line(['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']]]);
    $totals = F::totals(['withholdingTaxTotalAmount' => '10', 'payableRoundingAmount' => '0.5', 'payableAmount' => '105.5']);

    expect(resolve(VerifyDocumentTotalsAction::class)->handle([$line], $totals))->toBeNull();
});

it('rejects a payable amount that ignores the withholding', function (): void {
    $line   = F::line(['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']]]);
    $totals = F::totals(['withholdingTaxTotalAmount' => '10', 'payableAmount' => '115']);

    expect(fn (): null => resolve(VerifyDocumentTotalsAction::class)->handle([$line], $totals))
        ->toFailValidationOn('totals.payableAmount', 'The supplied amount cannot be reconciled with the fiscal evidence.');
});

it('compares mixed Money contexts and fixed tax amounts as exact decimals', function (): void {
    $line = F::line(['price' => FiscalMoney::cve('100'), 'netTotal' => FiscalMoney::exact('95', 'CVE'),
        'discount'           => ['valueType' => 'A', 'value' => '5'], 'taxes' => [['taxTypeCode' => 'IS', 'stampTaxCode' => 8, 'taxAmount' => '1000', 'taxTotal' => '1000']]]);
    $totals = F::totals(['netTotalAmount' => '95', 'taxTotalAmount' => '1000', 'payableAmount' => '1095', 'discountTotalAmount' => '5']);

    expect(resolve(VerifyDocumentTotalsAction::class)->handle([$line], $totals))->toBeNull();
});

it('accepts line evidence rounded half up to the five decimal XSD scale', function (array $line, array $totals): void {
    $lineItem   = F::line($line);
    $totalsData = F::totals($totals);

    expect(resolve(VerifyDocumentTotalsAction::class)->handle([$lineItem], $totalsData))->toBeNull();
})->with([
    'price extension' => [
        ['quantity' => ['value' => '3.33333', 'unitCode' => 'C62'], 'price' => '0.33333', 'priceExtension' => '1.11110', 'netTotal' => '1.11110'],
        ['priceExtensionTotalAmount' => '1.11110', 'netTotalAmount' => '1.11110', 'taxTotalAmount' => '0.17', 'payableAmount' => '1.28110'],
    ],
    'percentage discount net' => [
        ['price' => '0.33333', 'priceExtension' => '0.33333', 'discount' => ['value' => '33.333'], 'netTotal' => '0.22222'],
        ['priceExtensionTotalAmount' => '0.33333', 'netTotalAmount' => '0.22222', 'discountTotalAmount' => '0.11111', 'taxTotalAmount' => '0.03', 'payableAmount' => '0.25222'],
    ],
    'line tax total' => [
        ['price' => '1.1111', 'priceExtension' => '1.1111', 'netTotal' => '1.1111', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '0.16667']]],
        ['priceExtensionTotalAmount' => '1.1111', 'netTotalAmount' => '1.1111', 'taxTotalAmount' => '0.17', 'payableAmount' => '1.2811'],
    ],
]);

it('rejects inconsistent fiscal evidence at the field that disagrees', function (array $line, array $totals, string $field): void {
    $lines      = [F::line($line)];
    $totalsData = F::totals($totals);

    expect(fn (): null => resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totalsData))
        ->toFailValidationOn($field, 'The supplied amount cannot be reconciled with the fiscal evidence.');
})->with([
    'extension'                  => [['priceExtension' => '101'], [], 'lines.0.priceExtension'],
    'net'                        => [['netTotal' => '99'], [], 'lines.0.netTotal'],
    'tax'                        => [[], ['taxTotalAmount' => '14'], 'totals.taxTotalAmount'],
    'payable'                    => [[], ['payableAmount' => '116'], 'totals.payableAmount'],
    'charge'                     => [[], ['chargeTotalAmount' => '1'], 'totals.chargeTotalAmount'],
    'discount'                   => [[], ['discountTotalAmount' => '1'], 'totals.discountTotalAmount'],
    'withholding'                => [[], ['withholdingTaxTotalAmount' => '1'], 'totals.withholdingTaxTotalAmount'],
    'tax total evidence'         => [['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '16']]], [], 'lines.0.taxTotal'],
    'missing extension'          => [['priceExtension' => null], [], 'lines.0'],
    'negative net sum'           => [['lineTypeCode' => 'D'], [], 'totals.priceExtensionTotalAmount'],
    'amount discount allocation' => [[], ['discount' => ['value' => '1', 'valueType' => 'A']], 'totals.discount'],
    'negative allocation'        => [['netTotal' => '101'], ['discount' => ['value' => '1', 'valueType' => 'A']], 'lines.0.netTotal'],
    'missing withholding total'  => [['taxes' => [['taxTypeCode' => 'IR', 'taxPercentage' => '10']]], ['taxTotalAmount' => '0', 'payableAmount' => '100'], 'totals.withholdingTaxTotalAmount'],
    'wrong direction rounding'   => [
        ['quantity' => ['value' => '3.33333', 'unitCode' => 'C62'], 'price' => '0.33333', 'priceExtension' => '1.11109', 'netTotal' => '1.11109'],
        ['priceExtensionTotalAmount' => '1.11109', 'netTotalAmount' => '1.11109', 'taxTotalAmount' => '0.17', 'payableAmount' => '1.28109'],
        'lines.0.priceExtension',
    ],
]);

it('rejects an informational net changed by an amount discount', function (): void {
    $lines  = T::informationalLines('90', '9');
    $totals = T::amountDiscountWithInformationalTotals();

    expect(fn (): null => resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totals))
        ->toFailValidationOn('lines.2.netTotal', 'The supplied amount cannot be reconciled with the fiscal evidence.');
});

it('rejects a final tax sum that is neither exact nor rounded half up', function (): void {
    $lines  = T::smallTaxLines();
    $totals = T::smallTaxTotals('0.015', '0.155');

    expect(fn (): null => resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totals))
        ->toFailValidationOn('totals.taxTotalAmount', 'The supplied amount cannot be reconciled with the fiscal evidence.');
});

it('signs line withholding by the line type', function (string $lineType, string $amount, array $totals): void {
    expect(resolve(VerifyDocumentTotalsAction::class)->handle(T::withheldLines($lineType, $amount), T::withheldTotals(...$totals)))->toBeNull();
})->with([
    'deduction subtracts its withholding' => ['D', '20', ['80', '8', '87']],
    'information leaves withholding out'  => ['I', '10', ['100', '10', '105']],
]);

it('rejects withholding that ignores the line type sign', function (string $lineType, string $amount, array $totals): void {
    $lines      = T::withheldLines($lineType, $amount);
    $totalsData = T::withheldTotals(...$totals);

    expect(fn (): null => resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totalsData))
        ->toFailValidationOn('totals.withholdingTaxTotalAmount', 'The supplied amount cannot be reconciled with the fiscal evidence.');
})->with([
    'deduction withholding added'   => ['D', '20', ['80', '12', '83']],
    'information withholding added' => ['I', '10', ['100', '11', '104']],
]);

it('rejects a negative net sum even when it rounds to the declared zero', function (): void {
    $lines  = T::deductionBeyondNetLines();
    $totals = T::deductionBeyondNetTotals();

    expect(fn (): null => resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totals))
        ->toFailValidationOn('totals.netTotalAmount', 'The supplied amount cannot be reconciled with the fiscal evidence.');
});
