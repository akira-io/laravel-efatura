<?php

declare(strict_types=1);
use Akira\Efatura\Actions\ReconcileDocumentTotalsAction;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('keeps informational tax while excluding informational net', function (): void {
    $lines  = [F::line(), F::line(['lineTypeCode' => 'D', 'price' => '20', 'priceExtension' => '20', 'netTotal' => '20']), F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '10'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '80', 'netTotalAmount' => '80', 'discountTotalAmount' => '20', 'taxTotalAmount' => '13.5', 'payableAmount' => '93.5']);
    expect(resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toBe($totals);
});
it('accepts source supported tax and withholding rounding candidates', function (string $tax): void {
    $lines   = [F::line(['price' => '0.05', 'priceExtension' => '0.05', 'netTotal' => '0.05', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '10'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']]])];
    $lines[] = $lines[0];
    $totals  = F::totals(['priceExtensionTotalAmount' => '0.1', 'netTotalAmount' => '0.1', 'taxTotalAmount' => $tax, 'withholdingTaxTotalAmount' => $tax, 'payableAmount' => $tax === '0.02' ? '0.12' : '0.11']);
    expect(resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toBe($totals);
})->with(['0.01', '0.02']);
it('reconciles percentage discounts charges and explicit rounding residual', function (): void {
    $lines  = [F::line(['id' => 'A', 'discount' => ['value' => '10'], 'netTotal' => '81']), F::line(['lineTypeCode' => 'C', 'lineReferenceId' => 'A', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '9'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '110', 'netTotalAmount' => '90', 'chargeTotalAmount' => '10', 'discountTotalAmount' => '10', 'discount' => ['value' => '10'], 'taxTotalAmount' => '13.5', 'payableRoundingAmount' => '-0.005', 'payableAmount' => '103.495']);
    expect(resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toBe($totals);
});
it('uses submitted net totals as amount discount allocation evidence', function (): void {
    $lines  = [F::line(['netTotal' => '93']), F::line(['netTotal' => '97'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '200', 'netTotalAmount' => '190', 'taxTotalAmount' => '28.5', 'payableAmount' => '218.5', 'discount' => ['value' => '10', 'valueType' => 'A']]);
    expect(resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toBe($totals);
});
it('rejects inconsistent fiscal evidence', function (array $line, array $totals): void {
    expect(fn () => resolve(ReconcileDocumentTotalsAction::class)->handle([F::line($line)], F::totals($totals)))->toThrow(ValidationException::class);
})->with([
    'extension'                  => [['priceExtension' => '101'], []], 'net' => [['netTotal' => '99'], []],
    'tax'                        => [[], ['taxTotalAmount' => '14']], 'payable' => [[], ['payableAmount' => '116']],
    'charge'                     => [[], ['chargeTotalAmount' => '1']], 'discount' => [[], ['discountTotalAmount' => '1']],
    'withholding'                => [[], ['withholdingTaxTotalAmount' => '1']],
    'tax total evidence'         => [['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '16']]], []],
    'missing extension'          => [['priceExtension' => null], []], 'negative net sum' => [['lineTypeCode' => 'D'], []],
    'amount discount allocation' => [[], ['discount' => ['value' => '1', 'valueType' => 'A']]],
    'negative allocation'        => [['netTotal' => '101'], ['discount' => ['value' => '1', 'valueType' => 'A']]],
    'missing withholding total'  => [['taxes' => [['taxTypeCode' => 'IR', 'taxPercentage' => '10']]], ['taxTotalAmount' => '0', 'payableAmount' => '100']],
]);

it('reconciles amount discounts without changing deduction or informational nets', function (): void {
    $lines  = [F::line(['netTotal' => '90']), F::line(['lineTypeCode' => 'D', 'price' => '20', 'priceExtension' => '20', 'netTotal' => '20']), F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '10'])];
    $totals = F::totals(['priceExtensionTotalAmount' => '80', 'netTotalAmount' => '70', 'taxTotalAmount' => '12', 'payableAmount' => '82', 'discount' => ['value' => '10', 'valueType' => 'A']]);
    expect(resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toBe($totals);
    $lines[2] = F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '9']);
    expect(fn () => resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toThrow(ValidationException::class);
});

it('compares mixed Money contexts and fixed tax amounts as exact decimals', function (): void {
    $line = F::line(['price' => FiscalMoney::cve('100'), 'netTotal' => FiscalMoney::exact('95', 'CVE'),
        'discount'           => ['valueType' => 'A', 'value' => '5'], 'taxes' => [['taxTypeCode' => 'IS', 'stampTaxCode' => 8, 'taxAmount' => '1000', 'taxTotal' => '1000']]]);
    $totals = F::totals(['netTotalAmount' => '95', 'taxTotalAmount' => '1000', 'payableAmount' => '1095', 'discountTotalAmount' => '5']);
    expect(resolve(ReconcileDocumentTotalsAction::class)->handle([$line], $totals))->toBe($totals);
});

it('accepts exact and rounded final tax sums without arbitrary tolerance', function (string $tax, string $payable, bool $valid): void {
    $lines   = [F::line(['price' => '0.07', 'priceExtension' => '0.07', 'netTotal' => '0.07', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '10']]])];
    $lines[] = $lines[0];
    $totals  = F::totals(['priceExtensionTotalAmount' => '0.14', 'netTotalAmount' => '0.14', 'taxTotalAmount' => $tax, 'payableAmount' => $payable]);
    if ($valid) {
        expect(resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toBe($totals);
    } else {
        expect(fn () => resolve(ReconcileDocumentTotalsAction::class)->handle($lines, $totals))->toThrow(ValidationException::class);
    }
})->with([['0.014', '0.154', true], ['0.01', '0.15', true], ['0.02', '0.16', true], ['0.015', '0.155', false]]);
