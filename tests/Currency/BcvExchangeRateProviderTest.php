<?php

declare(strict_types=1);

use Akira\Efatura\Currency\BcvExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-21T14:30:00Z');
});

function bcvServes(string $body, int $status = 200, array $headers = []): void
{
    Http::fake(['https://www.bcv.cv/*' => Http::response($body, $status, $headers)]);
}

it('returns the official EUR buy rate with its BCV provenance', function (): void {
    bcvServes(C::fixture('bcv-rates-2026-07-21.html'));

    $quote = C::bcv()->rate(C::request(null, effectiveAt: '2026-07-21T18:00:00Z'));

    expect($quote->sourceCurrency)->toBe('EUR')
        ->and($quote->targetCurrency)->toBe('CVE')
        ->and((string) $quote->rate)->toBe('110.26500')
        ->and($quote->rateType)->toBe(ExchangeRateType::Buy)
        ->and($quote->effectiveAt->toIso8601String())->toBe('2026-07-21T00:00:00-01:00')
        ->and($quote->retrievedAt->toIso8601String())->toBe('2026-07-21T13:30:00-01:00')
        ->and($quote->provider)->toBe('Banco de Cabo Verde')
        ->and($quote->sourceUrl)->toBe(BcvExchangeRateProvider::DEFAULT_SOURCE_URL);
    Http::assertSent(fn (Request $request): bool => $request->url() === BcvExchangeRateProvider::DEFAULT_SOURCE_URL
        && $request->method() === 'GET'
        && $request->header('Accept') === ['text/html']);
});

it('divides a rate published per hundred units', function (ExchangeRateType $type, string $rate): void {
    bcvServes(C::fixture('bcv-rates-2026-07-21.html'));

    expect((string) C::bcv()->rate(C::request($type, 'JPY'))->rate)->toBe($rate);
})->with([
    'buy'  => [ExchangeRateType::Buy, '0.59727'],
    'sell' => [ExchangeRateType::Sell, '0.59854'],
]);

it('uses the configured rate type when the request names none', function (): void {
    bcvServes(C::fixture('bcv-rates-2026-07-21.html'));

    expect(C::bcv(['rateType' => ExchangeRateType::Sell])->rate(C::request(null, 'JPY'))->rateType)->toBe(ExchangeRateType::Sell);
});

it('reads the page from a configured address on the official origin', function (): void {
    bcvServes(C::fixture('bcv-rates-2026-07-21.html'));

    $quote = C::bcv(http: ['base_url' => 'https://www.bcv.cv/rates'])->rate(C::request());

    expect($quote->sourceUrl)->toBe('https://www.bcv.cv/rates');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.bcv.cv/rates');
});

it('refuses an address outside the official BCV origin before any request', function (string $url): void {
    Http::fake();

    expect(C::failure(fn (): BcvExchangeRateProvider => C::bcv(http: ['base_url' => $url])))->toBe(ExchangeRateFailure::SourceRequired);
    Http::assertNothingSent();
})->with([
    'another host' => ['https://rates.example/official'],
    'a lookalike'  => ['https://www.bcv.cv.attacker.example/rates'],
    'another port' => ['https://www.bcv.cv:8443/rates'],
]);

it('refuses a negative publication age or a rate type the BCV does not publish', function (array $options, ExchangeRateFailure $failure): void {
    expect(C::failure(fn (): BcvExchangeRateProvider => C::bcv($options)))->toBe($failure);
})->with([
    'negative age'   => [['maxPublicationAgeDays' => -1], ExchangeRateFailure::ResponseInvalid],
    'reference type' => [['rateType' => ExchangeRateType::Reference], ExchangeRateFailure::PairMismatch],
]);

it('refuses another target currency or rate type before any request', function (ExchangeRateRequest $request): void {
    Http::fake();

    expect(C::failure(fn (): ExchangeRateQuote => C::bcv()->rate($request)))->toBe(ExchangeRateFailure::PairMismatch);
    Http::assertNothingSent();
})->with([
    'a USD target'     => [fn (): ExchangeRateRequest => new ExchangeRateRequest('EUR', CarbonImmutable::now(), targetCurrency: 'USD')],
    'a reference rate' => [fn (): ExchangeRateRequest => C::request(ExchangeRateType::Reference)],
    'a custom rate'    => [fn (): ExchangeRateRequest => C::request(ExchangeRateType::Custom)],
]);

it('reports the source unavailable without its body or destination', function (Closure $response, array $context): void {
    Http::fake(['https://www.bcv.cv/*' => $response]);

    $exception = C::exception(fn (): ExchangeRateQuote => C::bcv()->rate(C::request()));

    expect($exception->failure)->toBe(ExchangeRateFailure::ProviderUnavailable)
        ->and($exception->retryable)->toBeTrue()
        ->and($exception->context)->toBe($context)
        ->and($exception->getMessage())->not->toContain('SECRET')
        ->and($exception->getPrevious())->toBeNull();
})->with([
    'unavailable' => [fn (): PromiseInterface => Http::response('SECRET', 503), ['source' => 'bcv', 'status' => 503]],
    'a redirect'  => [fn (): PromiseInterface => Http::response('', 302, ['Location' => 'https://redirect.example/SECRET']), ['source' => 'bcv', 'status' => 302]],
    'refused'     => [fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('SECRET', $request->toPsrRequest())), ['source' => 'bcv']],
]);

function bcvPageOf(int $bytes): string
{
    $page = C::fixture('bcv-rates-2026-07-21.html');

    return $page . str_repeat(' ', $bytes - strlen($page));
}

it('reads a page of exactly one mebibyte', function (): void {
    bcvServes(bcvPageOf(1_048_576));

    expect((string) C::bcv()->rate(C::request())->rate)->toBe('110.26500');
});

it('refuses a page one byte above one mebibyte', function (): void {
    bcvServes(bcvPageOf(1_048_577));

    expect(C::exception(fn (): ExchangeRateQuote => C::bcv()->rate(C::request())))
        ->failure->toBe(ExchangeRateFailure::ResponseInvalid)
        ->context->toBe(['source' => 'bcv']);
});

it('accepts only the publication of the requested Cabo Verde day by default', function (string $effectiveAt, string $published, ?ExchangeRateFailure $failure): void {
    bcvServes(str_replace('21/07/2026', $published, C::fixture('bcv-rates-2026-07-21.html')));
    $request = C::request(effectiveAt: $effectiveAt);

    expect($failure instanceof ExchangeRateFailure ? C::failure(fn (): ExchangeRateQuote => C::bcv()->rate($request)) : C::bcv()->rate($request)->effectiveAt->format('d/m/Y'))
        ->toBe($failure ?? $published);
})->with([
    'late evening in Cabo Verde'   => ['2026-07-21T23:59:00Z', '21/07/2026', null],
    'just before the CV midnight'  => ['2026-07-22T00:59:59Z', '21/07/2026', null],
    'previous CV day at 00:59 UTC' => ['2026-07-21T00:59:59Z', '20/07/2026', null],
    'the year boundary'            => ['2027-01-01T00:59:59Z', '31/12/2026', null],
    'an earlier publication'       => ['2026-07-22T12:00:00Z', '21/07/2026', ExchangeRateFailure::DateUnavailable],
    'a future publication'         => ['2026-07-20T12:00:00Z', '21/07/2026', ExchangeRateFailure::DateInvalid],
]);

it('accepts an earlier publication within the configured age and refuses an older one', function (int $days, ?ExchangeRateFailure $failure): void {
    bcvServes(C::fixture('bcv-rates-2026-07-21.html'));
    $request = C::request(effectiveAt: '2026-07-24T12:00:00Z');

    expect($failure instanceof ExchangeRateFailure ? C::failure(fn (): ExchangeRateQuote => C::bcv(['maxPublicationAgeDays' => $days])->rate($request)) : C::bcv(['maxPublicationAgeDays' => $days])->rate($request)->effectiveAt->toDateString())
        ->toBe($failure ?? '2026-07-21');
})->with([
    'within the age' => [3, null],
    'too old'        => [2, ExchangeRateFailure::Stale],
]);
