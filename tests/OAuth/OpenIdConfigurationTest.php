<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\OAuthException;
use Akira\Efatura\OAuth\OpenIdConfiguration;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\InMemoryRefreshTokens;
use Akira\Efatura\Tests\Support\OAuthFixtures as O;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-08T10:15:30Z');
    O::configure(new InMemoryRefreshTokens, 'client-secret');
});

it('reads the token endpoint from the openid configuration of the issuer once a day', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery()]);

    $first  = resolve(OpenIdConfiguration::class)->tokenEndpoint();
    $second = resolve(OpenIdConfiguration::class)->tokenEndpoint();
    CarbonImmutable::setTestNow('2026-02-09T10:15:31Z');
    $third = resolve(OpenIdConfiguration::class)->tokenEndpoint();

    expect([$first, $second, $third])->each->toBe(O::TOKEN_URL);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === O::DISCOVERY_URL
        && $request->header('Accept') === ['application/json']
        && ! $request->hasHeader(Fiscal::REPOSITORY_HEADER));
});

it('retries a discovery the issuer could not serve', function (): void {
    Http::fake([O::DISCOVERY_URL => Http::sequence()->push('', 503)->pushResponse(O::discovery())]);

    expect(resolve(OpenIdConfiguration::class)->tokenEndpoint())->toBe(O::TOKEN_URL);
    Http::assertSentCount(2);
});

it('refuses a token endpoint outside the issuer', function (string $endpoint): void {
    Http::fake([O::DISCOVERY_URL => O::discovery($endpoint)]);

    expect(fn (): string => resolve(OpenIdConfiguration::class)->tokenEndpoint())->toThrow(function (OAuthException $exception): void {
        expect($exception->errorCode)->toBe('oauth.discovery_failed')
            ->and($exception->context)->toBe(['status' => 200]);
    });
})->with([
    'plain http'       => ['http://iam.efatura.cv/token'],
    'another host'     => ['https://iam.example.test/token'],
    'another port'     => ['https://iam.efatura.cv:8443/token'],
    'with credentials' => ['https://user@iam.efatura.cv/token'],
    'not a url'        => ['https:///token'],
]);

it('fails closed on a discovery document it cannot use', function (Closure $response, array $context): void {
    Http::fake([O::DISCOVERY_URL => $response]);

    expect(fn (): string => resolve(OpenIdConfiguration::class)->tokenEndpoint())->toThrow(function (OAuthException $exception) use ($context): void {
        expect($exception->errorCode)->toBe('oauth.discovery_failed')
            ->and($exception->context)->toBe($context);
    });
})->with([
    'not found'           => [fn (): PromiseInterface => Http::response('', 404), ['status' => 404]],
    'html'                => [fn (): PromiseInterface => Http::response('<html/>'), ['status' => 200]],
    'a json string'       => [fn (): PromiseInterface => Http::response('"token"'), ['status' => 200]],
    'no token endpoint'   => [fn (): PromiseInterface => Http::response(['issuer' => O::ISSUER]), ['status' => 200]],
    'a numeric endpoint'  => [fn (): PromiseInterface => Http::response(['token_endpoint' => 42]), ['status' => 200]],
    'too large'           => [fn (): PromiseInterface => Http::response(str_repeat(' ', Fiscal::MAX_RESPONSE_BYTES + 1)), ['status' => 200]],
    'refused connections' => [fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('cURL error 7', $request->toPsrRequest())), []],
]);
