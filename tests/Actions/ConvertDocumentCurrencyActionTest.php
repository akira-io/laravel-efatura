<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ConvertDocumentCurrencyAction;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Currency\CallbackExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T13:30:00Z');
});

function eurQuote(string $rate = '110.265', array $overrides = []): ExchangeRateQuote
{
    return C::quote([
        'rate'        => $rate,
        'effectiveAt' => CarbonImmutable::parse('2026-10-02T00:00:00-01:00'),
        'retrievedAt' => CarbonImmutable::parse('2026-10-02T10:00:00-01:00'),
        ...$overrides,
    ]);
}

function eurInvoice(array $line = [], array $totals = [], array $overrides = []): array
{
    return F::payload([
        'lines'  => [F::linePayload(['price' => '1', 'priceExtension' => '1', 'netTotal' => '1', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '0.15']], ...$line])],
        'totals' => F::totalsPayload(['priceExtensionTotalAmount' => '1', 'netTotalAmount' => '1', 'taxTotalAmount' => '0.15', 'payableAmount' => '1.15', ...$totals]),
        ...$overrides,
    ]);
}

function convertToCve(DocumentData $document, string $currency = 'EUR', ?ExchangeRateQuote $quote = null): DocumentData
{
    return resolve(ConvertDocumentCurrencyAction::class)->handle($document, $currency, $quote ?? eurQuote());
}

it('converts the lines at two decimals and keeps the residue in the payable rounding', function (): void {
    $source = ElectronicInvoiceData::from(eurInvoice());
    $before = $source->toPayload();

    $converted = convertToCve($source);
    $payload   = $converted->toPayload();

    expect($converted)->toBeInstanceOf(ElectronicInvoiceData::class)->not->toBe($source)
        ->and($source->toPayload())->toBe($before)
        ->and($payload['lines'][0])->toMatchArray(['price' => '110.26500', 'priceExtension' => '110.27000', 'netTotal' => '110.27000'])
        ->and($payload['lines'][0]['taxes'][0])->toMatchArray(['taxPercentage' => '15', 'taxTotal' => '16.54000'])
        ->and($payload['totals'])->toMatchArray([
            'priceExtensionTotalAmount' => '110.27000',
            'netTotalAmount'            => '110.27000',
            'taxTotalAmount'            => '16.54000',
            'payableAmount'             => '126.80000',
            'payableRoundingAmount'     => '-0.01000',
            'withholdingTaxTotalAmount' => null,
            'payableAlternativeAmounts' => [['value' => '1.15000', 'currencyCode' => 'EUR', 'exchangeRate' => '110.265']],
        ]);
});

it('converts amount discounts and leaves percentages alone', function (array $discount, string $value, string $discountTotal): void {
    $source = ElectronicInvoiceData::from(eurInvoice(
        ['price' => '2', 'priceExtension' => '2', 'netTotal' => '1.8', 'discount' => $discount, 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '0.27']]],
        ['priceExtensionTotalAmount' => '2', 'netTotalAmount' => '1.8', 'discountTotalAmount' => '0.2', 'taxTotalAmount' => '0.27', 'payableAmount' => '2.07'],
    ));

    $payload = convertToCve($source)->toPayload();

    expect($payload['lines'][0]['discount']['value'])->toBe($value)
        ->and($payload['lines'][0]['netTotal'])->toBe('198.48000')
        ->and($payload['totals'])->toMatchArray([
            'discountTotalAmount'   => $discountTotal,
            'taxTotalAmount'        => '29.77000',
            'payableAmount'         => '228.25000',
            'payableRoundingAmount' => null,
        ]);
})->with([
    'an amount'    => [['value' => '0.2', 'valueType' => 'A'], '22.05000', '22.05000'],
    'a percentage' => [['value' => '10', 'valueType' => 'P'], '10', '22.05000'],
]);

it('keeps a global percentage discount', function (): void {
    $source = ElectronicInvoiceData::from(eurInvoice(totals: ['discount' => ['value' => '0', 'valueType' => 'P']]));

    expect(convertToCve($source)->toPayload()['totals']['discount'])->toBe(['value' => '0', 'valueType' => 'P']);
});

it('converts a global amount discount with the lines it was allocated to', function (): void {
    $source = ElectronicInvoiceData::from(eurInvoice(
        ['price' => '10', 'priceExtension' => '10', 'netTotal' => '9', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '1.35']]],
        ['priceExtensionTotalAmount' => '10', 'netTotalAmount' => '9', 'taxTotalAmount' => '1.35', 'payableAmount' => '10.35', 'discount' => ['value' => '1', 'valueType' => 'A']],
    ));

    expect(convertToCve($source, quote: eurQuote('2'))->toPayload()['totals'])->toMatchArray([
        'discount'      => ['value' => '2.00000', 'valueType' => 'A'],
        'payableAmount' => '20.70000',
    ]);
});

it('derives the withholding from the converted income tax', function (): void {
    $source = ElectronicInvoiceData::from(eurInvoice(
        ['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '0.15'], ['taxTypeCode' => 'IR', 'taxAmount' => '0.1', 'taxTotal' => '0.1']]],
        ['withholdingTaxTotalAmount' => '0.1', 'payableAmount' => '1.05'],
    ));

    expect(convertToCve($source)->toPayload()['totals'])->toMatchArray([
        'taxTotalAmount'            => '16.54000',
        'withholdingTaxTotalAmount' => '11.03000',
        'payableAmount'             => '115.78000',
        'payableRoundingAmount'     => null,
    ]);
});

it('signs deduction and information lines as the totals verification does', function (): void {
    $taxes  = fn (string $total): array => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => $total]];
    $source = ElectronicInvoiceData::from(eurInvoice(overrides: [
        'lines' => [
            F::linePayload(['price' => '10', 'priceExtension' => '10', 'netTotal' => '10', 'taxes' => $taxes('1.5')]),
            F::linePayload(['lineTypeCode' => 'D', 'price' => '1', 'priceExtension' => '1', 'netTotal' => '1', 'taxes' => $taxes('0.15')]),
            F::linePayload(['lineTypeCode' => 'I', 'price' => '5', 'priceExtension' => '5', 'netTotal' => '5', 'taxes' => $taxes('0.75')]),
        ],
        'totals' => F::totalsPayload(['priceExtensionTotalAmount' => '9', 'netTotalAmount' => '9', 'discountTotalAmount' => '1', 'chargeTotalAmount' => '0', 'taxTotalAmount' => '2.1', 'payableAmount' => '11.1']),
    ]));

    expect(convertToCve($source, quote: eurQuote('2'))->toPayload()['totals'])->toMatchArray([
        'priceExtensionTotalAmount' => '18.00000',
        'netTotalAmount'            => '18.00000',
        'discountTotalAmount'       => '2.00000',
        'chargeTotalAmount'         => '0.00000',
        'taxTotalAmount'            => '4.20000',
        'payableAmount'             => '22.20000',
        'payableRoundingAmount'     => null,
    ]);
});

it('converts payments and references', function (): void {
    $receipt = ReceiptInvoiceData::from(eurInvoice(overrides: ['payments' => ['payments' => [['paymentMeansCode' => '10', 'paymentAmount' => '1.15']]]]));
    $note    = DebitNoteData::from(P::correction([
        ...eurInvoice(),
        'references' => [['fiscalDocument' => ['value' => '1/2026/A/1', 'isOldDocument' => true], 'paymentAmount' => '1', 'taxes' => [['taxTypeCode' => 'IVA', 'taxAmount' => '0.15', 'taxTotal' => '0.15']]]],
    ]));

    expect(convertToCve($receipt)->toPayload()['payments']['payments'][0]['paymentAmount'])->toBe('126.80000')
        ->and(convertToCve($note)->toPayload()['references'][0])->toMatchArray(['paymentAmount' => '110.27000'])
        ->and(convertToCve($note)->toPayload()['references'][0]['taxes'][0])->toMatchArray(['taxAmount' => '16.54000', 'taxTotal' => '16.54000']);
});

it('asks the provider for the rate at the issue instant in Cabo Verde', function (): void {
    $requests = [];
    app()->instance(ExchangeRateProvider::class, new CallbackExchangeRateProvider(function (ExchangeRateRequest $request) use (&$requests): ExchangeRateQuote {
        $requests[] = $request;

        return eurQuote();
    }));

    $converted = resolve(ConvertDocumentCurrencyAction::class)->handle(ElectronicInvoiceData::from(eurInvoice()), ' eur ');

    expect($converted->toPayload()['totals']['payableAmount'])->toBe('126.80000')
        ->and($requests)->toHaveCount(1)
        ->and($requests[0]->sourceCurrency)->toBe('EUR')
        ->and($requests[0]->targetCurrency)->toBe('CVE')
        ->and($requests[0]->rateType)->toBeNull()
        ->and($requests[0]->effectiveAt->toIso8601String())->toBe('2026-10-02T12:00:00-01:00');
});

it('keeps a CVE document as it is without asking for a rate', function (): void {
    app()->instance(ExchangeRateProvider::class, new CallbackExchangeRateProvider(fn (): never => throw new RuntimeException('not called')));
    $source = ElectronicInvoiceData::from(F::payload());

    $converted = resolve(ConvertDocumentCurrencyAction::class)->handle($source, 'cve');

    expect($converted)->not->toBe($source)
        ->and($converted->toPayload())->toBe($source->toPayload())
        ->and($converted->toPayload()['totals']['payableAlternativeAmounts'])->toBe([]);
});

it('refuses a document it cannot convert before asking for a rate', function (Closure $document, string $currency, ExchangeRateFailure $failure): void {
    app()->instance(ExchangeRateProvider::class, new CallbackExchangeRateProvider(fn (): never => throw new RuntimeException('not called')));

    expect(C::failure(fn (): DocumentData => resolve(ConvertDocumentCurrencyAction::class)->handle($document(), $currency)))->toBe($failure);
})->with([
    'no totals'             => [fn (): DocumentData => TransportDocumentData::from(P::transport()), 'EUR', ExchangeRateFailure::InvoiceInvalid],
    'existing alternatives' => [fn (): DocumentData => ElectronicInvoiceData::from(eurInvoice(totals: [
        'payableAlternativeAmounts' => [['value' => '1.15', 'currencyCode' => 'USD', 'exchangeRate' => '100']],
    ])), 'EUR', ExchangeRateFailure::AlternativesConflict],
    'an unofficial currency' => [fn (): DocumentData => ElectronicInvoiceData::from(eurInvoice()), 'AAA', ExchangeRateFailure::CurrencyUnsupported],
]);

it('refuses a quote that does not answer the document', function (ExchangeRateQuote $quote, ExchangeRateFailure $failure): void {
    expect(C::failure(fn (): DocumentData => convertToCve(ElectronicInvoiceData::from(eurInvoice()), quote: $quote)))->toBe($failure);
})->with([
    'another currency' => [fn (): ExchangeRateQuote => eurQuote(overrides: ['sourceCurrency' => 'USD']), ExchangeRateFailure::PairMismatch],
    'after the issue'  => [fn (): ExchangeRateQuote => eurQuote(overrides: ['effectiveAt' => CarbonImmutable::parse('2026-10-02T12:00:01-01:00')]), ExchangeRateFailure::DateUnavailable],
]);

it('reports a converted document the fiscal rules refuse as an invalid invoice', function (): void {
    $source = ElectronicInvoiceData::from(eurInvoice(
        ['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '0.15'], ['taxTypeCode' => 'IR', 'taxAmount' => '0.001']]],
        ['withholdingTaxTotalAmount' => '0.001', 'payableAmount' => '1.149'],
    ));

    $exception = C::exception(fn (): DocumentData => convertToCve($source, quote: eurQuote('1')));

    expect($exception->failure)->toBe(ExchangeRateFailure::InvoiceInvalid)
        ->and($exception->getPrevious())->toBeInstanceOf(ValidationException::class)
        ->and($exception->getPrevious()->errors())->toHaveKey('lines.0.taxes.1.taxAmount');
});
