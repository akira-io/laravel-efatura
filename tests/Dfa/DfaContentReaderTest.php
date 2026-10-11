<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Dfa\DfaContent;
use Akira\Efatura\Dfa\DfaContentReader;
use Akira\Efatura\Dfa\DfaConversion;
use Akira\Efatura\Dfa\DfaLine;
use Akira\Efatura\Dfa\DfaParty;
use Akira\Efatura\Dfa\DfaTotals;
use Akira\Efatura\Tests\Support\DfaFixtures as D;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;

function dfaInvoice(array $lines, array $totals): ElectronicInvoiceData
{
    $document = D::invoice();

    return new ElectronicInvoiceData(...[
        ...get_object_vars($document),
        'lines'  => array_map(LineItemData::from(...), $lines),
        'totals' => TotalsData::from(F::totalsPayload($totals)),
    ]);
}

it('writes each tax of a line by the way it is charged', function (array $taxes, string $expected): void {
    $content = new DfaContentReader()->read(dfaInvoice([F::linePayload(['taxes' => $taxes])], []), 7, true);

    expect($content->lines[0]->tax)->toBe($expected);
})->with([
    'percentage' => [[['taxTypeCode' => 'IVA', 'taxPercentage' => '15']], '15%'],
    'amount'     => [[['taxTypeCode' => 'IS', 'stampTaxCode' => 7, 'taxAmount' => '0.5', 'taxTotal' => '0.5']], '0$50'],
    'exemption'  => [[['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1']], '1'],
    'two taxes'  => [[['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], ['taxTypeCode' => 'IS', 'stampTaxCode' => 7, 'taxAmount' => '2']], '15% + 2$00'],
    'none'       => [[], ''],
]);

it('lists every line, the totals and each alternative amount to pay', function (): void {
    $document = dfaInvoice(
        [F::linePayload(['quantity' => ['value' => '2.5', 'unitCode' => 'KGM'], 'price' => '560', 'netTotal' => '1400', 'item' => ['description' => 'Rice', 'emitterIdentification' => 'COD1']]),
            F::linePayload(['netTotal' => null, 'priceExtension' => '30'])],
        [
            'netTotalAmount'            => '1400', 'chargeTotalAmount' => '25', 'discountTotalAmount' => '1000', 'taxTotalAmount' => '210',
            'withholdingTaxTotalAmount' => '10', 'payableRoundingAmount' => '-0.01', 'payableAmount' => '1609.99',
            'payableAlternativeAmounts' => [
                ['value' => '14.6', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265'],
                ['value' => '2166', 'currencyCode' => 'JPY', 'exchangeRate' => '0.74321'],
            ],
        ],
    );

    $content = new DfaContentReader()->read($document, 7, true);

    expect($content->lines)->toEqual([
        new DfaLine('COD1', 'Rice', '2,5', 'KGM', '560$00', '15%', '1 400$00'),
        new DfaLine('SKU', 'Product', '1', 'C62', '100$00', '15%', '30$00'),
    ])->and($content->totals)->toEqual(new DfaTotals(2, '1 400$00', '25$00', '1 000$00', '210$00', '10$00', '-0$01', '1 609$99'))
        ->and($content->conversions)->toEqual([
            new DfaConversion('EUR', '14,60 EUR', '1 EUR = 110,26500 CVE'),
            new DfaConversion('JPY', '2 166 JPY', '1 JPY = 0,74321 CVE'),
        ])
        ->and($content->issuedAtLabel())->toBe('02/10/2026 12:00:00');
});

it('prints a unit price with its significant decimals and a deduction line as negative', function (): void {
    $content = new DfaContentReader()->read(dfaInvoice([
        F::linePayload(['quantity' => ['value' => '10000', 'unitCode' => 'C62'], 'price' => '0.11027', 'priceExtension' => '1102.7', 'netTotal' => '1102.7']),
        F::linePayload(['lineTypeCode' => 'D', 'price' => '1234.5', 'priceExtension' => '1234.5', 'netTotal' => '1234.5']),
        F::linePayload(['lineTypeCode' => 'D', 'netTotal' => null, 'priceExtension' => '30']),
    ], []), 7, true);

    expect(collect($content->lines)->map(fn (DfaLine $line): array => [$line->unitPrice, $line->total])->all())->toBe([
        ['0$11027', '1 102$70'],
        ['1 234$50', '-1 234$50'],
        ['100$00', '-30$00'],
    ]);
});

it('leaves zero withholding and rounding out of the totals', function (): void {
    $totals = new DfaContentReader()->read(dfaInvoice([F::linePayload()], ['withholdingTaxTotalAmount' => '0', 'payableRoundingAmount' => '0']), 7, true)->totals;

    expect($totals?->withholding)->toBeNull()
        ->and($totals?->rounding)->toBeNull();
});

it('reads the lines of a transport document, which has no totals', function (): void {
    $content = new DfaContentReader()->read(TransportDocumentData::from(P::unpricedTransport()), 3, true);

    expect($content->lines)->toEqual([new DfaLine('SKU', 'Product', '1', 'C62', '', '', '')])
        ->and($content->totals)->toBeNull()
        ->and($content->receiver)->toBeNull()
        ->and($content->documentType)->toBe('DTE')
        ->and($content->series)->toBe('');
});

it('reads a receipt, which has neither lines nor totals', function (): void {
    $content = new DfaContentReader()->read(ReceiptData::from(F::receiptPayload('1')), 4, true);

    expect($content)->toBeInstanceOf(DfaContent::class)
        ->and($content->lines)->toBe([])
        ->and($content->totals)->toBeNull()
        ->and($content->conversions)->toBe([])
        ->and($content->documentNumber)->toBe('4');
});

it('prints the telephone, else the mobile, else the email of a party', function (array $contacts, ?string $expected): void {
    $document = new ElectronicInvoiceData(...[
        ...get_object_vars(D::invoice()),
        'receiver' => PartyData::from([...F::payload()['receiver'], 'contacts' => $contacts]),
    ]);

    expect(new DfaContentReader()->read($document, 7, true)->receiver)->toEqual(new DfaParty('900800700', 'Receiver', null, $expected));
})->with([
    'telephone' => [['telephone' => '2610000', 'mobilephone' => '9910000', 'email' => 'buyer@example.cv'], '2610000'],
    'mobile'    => [['mobilephone' => '9910000', 'email' => 'buyer@example.cv'], '9910000'],
    'email'     => [['email' => 'buyer@example.cv'], 'buyer@example.cv'],
    'none'      => [[], null],
]);

it('leaves the customer out when asked to', function (): void {
    expect(new DfaContentReader()->read(D::invoice(), 7, false)->receiver)->toBeNull();
});
