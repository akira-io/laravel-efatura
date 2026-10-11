<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ConvertDocumentCurrencyAction;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Akira\Efatura\Tests\Support\ForeignDocuments;
use Akira\Efatura\Tests\Support\XmlFixtures;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T13:30:00Z');
});

function reconciledToCve(array $payload, string $rate, string $currency = 'EUR'): DocumentData
{
    $quote = C::quote([
        'sourceCurrency' => $currency,
        'rate'           => $rate,
        'effectiveAt'    => CarbonImmutable::parse('2026-10-02T00:00:00-01:00'),
        'retrievedAt'    => CarbonImmutable::parse('2026-10-02T10:00:00-01:00'),
    ]);

    return resolve(ConvertDocumentCurrencyAction::class)->handle(ElectronicInvoiceData::from($payload), $currency, $quote);
}

function keepsTheNetTotal(array $source, array $converted, string $rate): bool
{
    $perLine   = BigDecimal::of($rate)->multipliedBy('0.01')->plus('0.02');
    $tolerance = collect($source['lines'])->reduce(
        static fn (BigDecimal $carry, array $line): BigDecimal => $carry->plus($perLine)->plus(BigDecimal::of($line['quantity']['value'])->multipliedBy('0.00001')),
        BigDecimal::of('0.01'),
    );
    $drift = BigDecimal::of($source['totals']['netTotalAmount'])->multipliedBy($rate)->minus($converted['totals']['netTotalAmount'])->abs();

    return $drift->isLessThanOrEqualTo($tolerance);
}

it('converts the unit price at five decimals and recomputes the extension of two units', function (): void {
    $payload = reconciledToCve(ForeignDocuments::invoice([['quantity' => '2', 'price' => '1', 'tax' => '15']]), '110.265')->toPayload();

    expect($payload['lines'][0])->toMatchArray(['price' => '110.26500', 'priceExtension' => '220.53000', 'netTotal' => '220.53000'])
        ->and($payload['lines'][0]['taxes'][0])->toMatchArray(['taxPercentage' => '15', 'taxTotal' => '33.08000'])
        ->and($payload['totals'])->toMatchArray([
            'priceExtensionTotalAmount' => '220.53000',
            'netTotalAmount'            => '220.53000',
            'taxTotalAmount'            => '33.08000',
            'payableAmount'             => '253.61000',
            'payableRoundingAmount'     => null,
        ]);
});

it('reconciles awkward rates, quantities, taxes and discounts', function (array $lines, ?array $discount, string $rate, ?string $net = null, string $currency = 'EUR'): void {
    $source    = ForeignDocuments::invoice($lines, $discount);
    $converted = reconciledToCve($source, $rate, $currency)->toPayload();
    $payable   = BigDecimal::of($source['totals']['payableAmount'])->multipliedBy($rate);

    expect($payable->minus($converted['totals']['payableAmount'])->abs()->isLessThanOrEqualTo('0.005'))->toBeTrue()
        ->and(keepsTheNetTotal($source, $converted, $rate))->toBeTrue()
        ->and($converted['totals']['netTotalAmount'])->toBe($net ?? $converted['totals']['netTotalAmount']);
})->with([
    'ten thousand sub-cent units'           => [[['quantity' => '10000', 'price' => '0.001', 'tax' => '15']], null, '110.265', '1102.70000'],
    'a thousand yen'                        => [[['price' => '1000', 'tax' => '15']], null, '0.59727', '597.27000', 'JPY'],
    'many sub-cent units at a small rate'   => [[['quantity' => '10000', 'price' => '0.00523', 'tax' => '15']], null, '0.59727', '31.20000'],
    'a third of a unit at a tiny rate'      => [[['quantity' => '0.333', 'price' => '7.77', 'tax' => '2.375']], null, '0.00917'],
    'many units at a large rate'            => [[['quantity' => '100', 'price' => '0.99', 'tax' => '23']], null, '1234.56789'],
    'a percentage line discount'            => [[['quantity' => '3', 'price' => '33.33', 'discount' => ['value' => '33.333', 'valueType' => 'P'], 'tax' => '8.5']], null, '3.33333'],
    'an amount line discount'               => [[['quantity' => '7.25', 'price' => '1.01', 'discount' => ['value' => '0.99', 'valueType' => 'A'], 'tax' => '15']], null, '0.59727'],
    'a global percentage discount'          => [[['quantity' => '2', 'price' => '1', 'tax' => '15'], ['quantity' => '3', 'price' => '2.5', 'tax' => '8.5']], ['value' => '7.5', 'valueType' => 'P'], '110.265'],
    'a global amount discount on two lines' => [[['quantity' => '2', 'price' => '1.11', 'allocation' => '0.33', 'tax' => '15'], ['quantity' => '3', 'price' => '2.22', 'allocation' => '0.67', 'tax' => '15']], ['value' => '0', 'valueType' => 'A'], '0.59727'],
    'a deduction and an information line'   => [[['quantity' => '12', 'price' => '9.99', 'tax' => '15'], ['type' => 'D', 'price' => '1.49', 'tax' => '15'], ['type' => 'I', 'quantity' => '2', 'price' => '4.5', 'tax' => '15']], null, '110.265'],
    'a withholding income tax'              => [[['quantity' => '2', 'price' => '49.99', 'tax' => '15', 'incomeTax' => '9.99']], null, '3.33333'],
]);

it('reconciles a few hundred generated documents', function (): void {
    $random = new Randomizer(new Mt19937(20261011));

    for ($index = 0; $index < 300; $index++) {
        [$source, $rate] = ForeignDocuments::random($random);
        $totals          = reconciledToCve($source, $rate)->toPayload()['totals'];
        $alternative     = $totals['payableAlternativeAmounts'][0];
        $residual        = BigDecimal::of($alternative['value'])->multipliedBy($alternative['exchangeRate'])->minus($totals['payableAmount'])->abs();

        expect(BigDecimal::of($alternative['value'])->isEqualTo($source['totals']['payableAmount']))->toBeTrue('document ' . $index)
            ->and($residual->isLessThanOrEqualTo('0.005'))->toBeTrue('document ' . $index)
            ->and(keepsTheNetTotal($source, ['totals' => $totals], $rate))->toBeTrue('document ' . $index);
    }
});

it('writes a zero decimal currency alternative without non-significant zeros', function (): void {
    $converted = reconciledToCve([...ForeignDocuments::invoice([['price' => '1000', 'tax' => '15']]), 'header' => P::header(['serie' => 'A', 'documentNumber' => 1]), 'emission' => P::transmission()], '0.59727', 'JPY');

    expect($converted->toPayload()['totals']['payableAlternativeAmounts'])->toBe([['value' => '1150.00000', 'currencyCode' => 'JPY', 'exchangeRate' => '0.59727']])
        ->and(XmlFixtures::documentXml($converted))->toContain('<PayableAlternativeAmount CurrencyCode="JPY" ExchangeRate="0.59727">1150</PayableAlternativeAmount>');
});
