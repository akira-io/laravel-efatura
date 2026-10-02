<?php

declare(strict_types=1);
use Akira\Efatura\Actions\VerifyDocumentTotalsAction;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

function verifiesTotals(array $lines, TotalsData $totals): void
{
    resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totals);

    expect($lines)->not->toBeEmpty();
}

function rejectsTotals(array $lines, TotalsData $totals, string $field): void
{
    expect(fn () => resolve(VerifyDocumentTotalsAction::class)->handle($lines, $totals))
        ->toThrow(function (ValidationException $exception) use ($field): void {
            expect($exception->errors())->toBe([$field => ['The supplied amount cannot be reconciled with the fiscal evidence.']]);
        });
}

it('rounds fiscal amounts half up', function (): void {
    expect(DecimalFormatter::fiscalRounding())->toBe(RoundingMode::HalfUp);
});

it('keeps informational tax while excluding informational net', function (): void {
    $lines  = [F::line(), F::line(['lineTypeCode' => 'D', 'price' => '20', 'priceExtension' => '20', 'netTotal' => '20']), F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '10'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '80', 'netTotalAmount' => '80', 'discountTotalAmount' => '20', 'taxTotalAmount' => '13.5', 'payableAmount' => '93.5']);
    verifiesTotals($lines, $totals);
});
it('accepts source supported tax and withholding rounding candidates', function (string $tax): void {
    $lines   = [F::line(['price' => '0.05', 'priceExtension' => '0.05', 'netTotal' => '0.05', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '10'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']]])];
    $lines[] = $lines[0];
    $totals  = F::totals(['priceExtensionTotalAmount' => '0.1', 'netTotalAmount' => '0.1', 'taxTotalAmount' => $tax, 'withholdingTaxTotalAmount' => $tax, 'payableAmount' => $tax === '0.02' ? '0.12' : '0.11']);
    verifiesTotals($lines, $totals);
})->with(['0.01', '0.02']);
it('reconciles percentage discounts charges and explicit rounding residual', function (): void {
    $lines  = [F::line(['id' => 'A', 'discount' => ['value' => '10'], 'netTotal' => '81']), F::line(['lineTypeCode' => 'C', 'lineReferenceId' => 'A', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '9'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '110', 'netTotalAmount' => '90', 'chargeTotalAmount' => '10', 'discountTotalAmount' => '10', 'discount' => ['value' => '10'], 'taxTotalAmount' => '13.5', 'payableRoundingAmount' => '-0.005', 'payableAmount' => '103.495']);
    verifiesTotals($lines, $totals);
});
it('uses submitted net totals as amount discount allocation evidence', function (): void {
    $lines  = [F::line(['netTotal' => '93']), F::line(['netTotal' => '97'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '200', 'netTotalAmount' => '190', 'taxTotalAmount' => '28.5', 'payableAmount' => '218.5', 'discount' => ['value' => '10', 'valueType' => 'A']]);
    verifiesTotals($lines, $totals);
});
it('rejects inconsistent fiscal evidence at the field that disagrees', function (array $line, array $totals, string $field): void {
    rejectsTotals([F::line($line)], F::totals($totals), $field);
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
]);

it('reconciles amount discounts without changing deduction or informational nets', function (): void {
    $lines  = [F::line(['netTotal' => '90']), F::line(['lineTypeCode' => 'D', 'price' => '20', 'priceExtension' => '20', 'netTotal' => '20']), F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '10'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '80', 'netTotalAmount' => '70', 'taxTotalAmount' => '12', 'payableAmount' => '82', 'discount' => ['value' => '10', 'valueType' => 'A']]);
    verifiesTotals($lines, $totals);
    $lines[2] = F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '9']);
    rejectsTotals($lines, $totals, 'lines.2.netTotal');
});

it('compares mixed Money contexts and fixed tax amounts as exact decimals', function (): void {
    $line = F::line(['price' => FiscalMoney::cve('100'), 'netTotal' => FiscalMoney::exact('95', 'CVE'),
        'discount'           => ['valueType' => 'A', 'value' => '5'], 'taxes' => [['taxTypeCode' => 'IS', 'stampTaxCode' => 8, 'taxAmount' => '1000', 'taxTotal' => '1000']]]);
    $totals = F::totals(['netTotalAmount' => '95', 'taxTotalAmount' => '1000', 'payableAmount' => '1095', 'discountTotalAmount' => '5']);
    verifiesTotals([$line], $totals);
});

it('accepts exact and rounded final tax sums without arbitrary tolerance', function (string $tax, string $payable, bool $valid): void {
    $lines   = [F::line(['price' => '0.07', 'priceExtension' => '0.07', 'netTotal' => '0.07', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '10']]])];
    $lines[] = $lines[0];
    $totals  = F::totals(['priceExtensionTotalAmount' => '0.14', 'netTotalAmount' => '0.14', 'taxTotalAmount' => $tax, 'payableAmount' => $payable]);
    if ($valid) {
        verifiesTotals($lines, $totals);
    } else {
        rejectsTotals($lines, $totals, 'totals.taxTotalAmount');
    }
})->with([['0.014', '0.154', true], ['0.01', '0.15', true], ['0.02', '0.16', true], ['0.015', '0.155', false]]);

it('accepts line evidence rounded half up to the five decimal XSD scale', function (array $line, array $totals): void {
    verifiesTotals([F::line($line)], F::totals($totals));
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

it('rejects five decimal evidence rounded in the wrong direction', function (): void {
    $line = ['quantity' => ['value' => '3.33333', 'unitCode' => 'C62'], 'price' => '0.33333', 'priceExtension' => '1.11109', 'netTotal' => '1.11109'];

    rejectsTotals([F::line($line)], F::totals(['priceExtensionTotalAmount' => '1.11109', 'netTotalAmount' => '1.11109', 'taxTotalAmount' => '0.17', 'payableAmount' => '1.28109']), 'lines.0.priceExtension');
});
