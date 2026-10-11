<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ConvertDocumentCurrencyAction;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T13:30:00Z');
});

function convertedAt(array $payload, string $rate): array
{
    $quote = C::quote([
        'rate'        => $rate,
        'effectiveAt' => CarbonImmutable::parse('2026-10-02T00:00:00-01:00'),
        'retrievedAt' => CarbonImmutable::parse('2026-10-02T10:00:00-01:00'),
    ]);

    return resolve(ConvertDocumentCurrencyAction::class)->handle(ElectronicInvoiceData::from($payload), 'EUR', $quote)->toPayload();
}

function chargedInvoice(array $normal, array $charge, array $totals): array
{
    $line = static fn (array $values, array $extra): array => F::linePayload([
        'price'          => $values[0],
        'priceExtension' => $values[0],
        'netTotal'       => $values[1],
        'taxes'          => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']],
        ...$extra,
    ]);

    return F::payload([
        'lines'  => [$line($normal, ['id' => 'L1']), $line($charge, ['lineTypeCode' => 'C', 'lineReferenceId' => 'L1'])],
        'totals' => F::totalsPayload($totals),
    ]);
}

it('shares an amount global discount with charge lines and sends the residual cent to the right line', function (array $source, string $rate, array $nets): void {
    $converted = convertedAt($source, $rate);

    expect(array_column($converted['lines'], 'netTotal'))->toBe($nets)
        ->and($converted['totals']['chargeTotalAmount'])->toBeNull()
        ->and($converted['totals']['discountTotalAmount'])->toBeNull();
})->with([
    'a missing cent goes to the line with the most room' => [
        fn (): array => chargedInvoice(['5', '4.75'], ['1', '0.65'], [
            'priceExtensionTotalAmount' => '6', 'netTotalAmount' => '5.4', 'taxTotalAmount' => '0.81', 'payableAmount' => '6.21',
            'discount'                  => ['value' => '0.6', 'valueType' => 'A'],
        ]),
        '1.01',
        ['4.79000', '0.66000'],
    ],
    'an extra cent is taken from the largest share' => [
        fn (): array => chargedInvoice(['5', '4.67'], ['1', '0.33'], [
            'priceExtensionTotalAmount' => '6', 'netTotalAmount' => '5', 'taxTotalAmount' => '0.75', 'payableAmount' => '5.75',
            'discount'                  => ['value' => '1', 'valueType' => 'A'],
        ]),
        '2.5',
        ['11.67000', '0.83000'],
    ],
]);

it('signs the withholding of deduction and information lines as the totals verification does', function (): void {
    $taxes = fn (string $vat, string $income): array => [
        ['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => $vat],
        ['taxTypeCode' => 'IR', 'taxAmount' => $income, 'taxTotal' => $income],
    ];
    $source = F::payload([
        'lines' => [
            F::linePayload(['price' => '10', 'priceExtension' => '10', 'netTotal' => '10', 'taxes' => $taxes('1.5', '1')]),
            F::linePayload(['lineTypeCode' => 'D', 'price' => '1', 'priceExtension' => '1', 'netTotal' => '1', 'taxes' => $taxes('0.15', '0.1')]),
            F::linePayload(['lineTypeCode' => 'I', 'price' => '5', 'priceExtension' => '5', 'netTotal' => '5', 'taxes' => $taxes('0.75', '0.5')]),
        ],
        'totals' => F::totalsPayload([
            'priceExtensionTotalAmount' => '9', 'netTotalAmount' => '9', 'taxTotalAmount' => '2.1', 'withholdingTaxTotalAmount' => '0.9', 'payableAmount' => '10.2',
        ]),
    ]);

    expect(convertedAt($source, '2')['totals'])->toMatchArray([
        'withholdingTaxTotalAmount' => '1.80000',
        'payableAmount'             => '20.40000',
        'payableRoundingAmount'     => null,
    ]);
});
