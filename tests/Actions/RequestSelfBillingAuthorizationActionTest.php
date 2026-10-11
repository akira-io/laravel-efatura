<?php

declare(strict_types=1);

use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Actions\RequestSelfBillingAuthorizationAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;
use Akira\Efatura\Tests\Support\SelfBillingFixtures as S;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SelfBillingAuthorization;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function selfBillingConfig(string $driver, array $overrides = []): void
{
    app()->instance(EfaturaConfig::class, ConfigFixtures::load(array_replace_recursive([
        'driver'      => $driver,
        'environment' => 'test',
        'emitter'     => ['tax_id' => '100200300'],
        'transmitter' => ['middleware_key' => test()->key],
        'http'        => ['retry_delay_milliseconds' => 0],
    ], $overrides)));
}

function requestSelfBilling(): SelfBillingAuthorization
{
    return resolve(RequestSelfBillingAuthorizationAction::class)->handle(S::request());
}

beforeEach(function (): void {
    $this->key    = 'key-' . bin2hex(random_bytes(30));
    $this->tokens = new RecordingAccessTokens(bin2hex(random_bytes(12)));
    app()->instance(AccessTokenProvider::class, $this->tokens);
});

it('asks the middleware to authorize a self-billed document with the exact json of the manual', function (): void {
    selfBillingConfig('middleware');
    Http::fake(['*' => Http::response(S::granted())]);

    expect(requestSelfBilling())->toEqual(new SelfBillingAuthorization(S::AUTHORIZATION_ID, 3600, S::iud(), 'AUTOFATURA', 7, 42));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://localhost:3443/v1/dfe/self-billing/authorize'
        && $request->body() === '{"taxId":"200300400","documentTypeCode":1,"mobilePhoneNumber":"9912345","totalAmount":"1500.5"}'
        && $request->header('Content-Type') === ['application/json']
        && $request->header('Accept') === ['application/json']
        && $request->header('cv-ef-mw-core-transmitter-key') === [$this->key]
        && $request->header('cv-ef-repository-code') === ['3']
        && ! $request->hasHeader('Authorization'));
});

it('asks the platform with the access token of the configured buyer', function (): void {
    selfBillingConfig('platform');
    Http::fake(['*' => Http::response(S::granted())]);

    expect(requestSelfBilling()->documentNumber)->toBe(42)
        ->and($this->tokens->calls)->toBe([['token', '100200300', ['cv_ef_dfe_self_billing_authorize']]]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://services.efatura.cv/v1/dfe/self-billing/authorize'
        && $request->header('Authorization') === ['Bearer ' . $this->tokens->current()]
        && $request->header('cv-ef-repository-code') === ['3']
        && ! $request->hasHeader('cv-ef-mw-core-transmitter-key'));
});

it('renews the platform token once after a refused request', function (): void {
    selfBillingConfig('platform');
    Http::fake(['*' => Http::sequence()->push('', 401)->push(S::granted())]);

    expect(requestSelfBilling()->iud)->toBe(S::iud())
        ->and(array_column($this->tokens->calls, 0))->toBe(['token', 'forget', 'token']);
    Http::assertSentCount(2);
});

it('refuses to ask the platform without a configured buyer', function (): void {
    selfBillingConfig('platform', ['emitter' => ['tax_id' => null]]);
    Http::fake();

    expect(fn (): SelfBillingAuthorization => requestSelfBilling())->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::MissingCredentials)
            ->and($exception->field)->toBe('efatura.emitter.tax_id');
    });
    Http::assertNothingSent();
});

it('refuses to ask the middleware without the transmitter key', function (): void {
    selfBillingConfig('middleware', ['transmitter' => ['middleware_key' => null]]);
    Http::fake();

    expect(fn (): SelfBillingAuthorization => requestSelfBilling())->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::MissingCredentials)
            ->and($exception->field)->toBe('efatura.transmitter.middleware_key')
            ->and($exception->context)->toBe([]);
    });
    Http::assertNothingSent();
});

it('rejects with the messages of the platform whatever the status', function (int $status): void {
    selfBillingConfig('middleware');
    Http::fake(['*' => Http::response(S::refused(), $status)]);

    expect(fn (): SelfBillingAuthorization => requestSelfBilling())->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::Rejected)
            ->and($exception->messages)->toHaveCount(2);
    });
})->with([200, 400, 422]);

it('never repeats the request and maps an unreadable answer by its status', function (int $status, TransportFailure $failure): void {
    selfBillingConfig('middleware');
    Http::fake(['*' => Http::sequence()->push('<html>proxy</html>', $status)->push(S::granted())]);

    expect(fn (): SelfBillingAuthorization => requestSelfBilling())->toThrow(function (TransportException $exception) use ($status, $failure): void {
        expect($exception->failure)->toBe($failure)
            ->and($exception->context)->toBe(['status' => $status, 'bytes' => 18]);
    });
    Http::assertSentCount(1);
})->with([
    [200, TransportFailure::UnexpectedResponse],
    [401, TransportFailure::Unauthorized],
    [429, TransportFailure::RateLimited],
    [503, TransportFailure::OutcomeUnknown],
]);

it('classifies a request that never connected as undelivered and keeps the key out of it', function (): void {
    selfBillingConfig('middleware');
    $original = ini_set('zend.exception_ignore_args', '0');
    Http::fake(fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('cURL error 7: Failed to connect', $request->toPsrRequest())));

    try {
        requestSelfBilling();
    } catch (TransportException $transportException) {
        expect($transportException->failure)->toBe(TransportFailure::ConnectionFailed)
            ->and($transportException->context)->toBe([])
            ->and(ExceptionTrace::exposed($transportException))->not->toContain($this->key);
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }

    expect($transportException ?? null)->toBeInstanceOf(TransportException::class);
});

it('validates the request before anything is sent', function (): void {
    selfBillingConfig('middleware');
    Http::fake();
    $request = S::request();
    $invalid = $request::factory()->withoutValidation()->from([...S::input(), 'documentTypeCode' => 'TVE']);

    expect(fn (): SelfBillingAuthorization => resolve(RequestSelfBillingAuthorizationAction::class)->handle($invalid))
        ->toFailValidationOn('documentTypeCode', 'The document type code must be a document type that self-billing can authorize.');
    Http::assertNothingSent();
});

it('answers from the fake transport in the order the answers were queued', function (): void {
    $fake    = resolve(FakeTransport::class);
    $message = new RemoteMessage('BIZ', 'ERROR', null, 'BIZ-901', null, null);
    $granted = new SelfBillingAuthorization(S::AUTHORIZATION_ID, 3600, S::iud(), 'AUTOFATURA', 7, 42);
    app()->instance(Transport::class, $fake->grantSelfBilling($granted)->refuseSelfBilling($message));

    expect(requestSelfBilling())->toBe($granted)
        ->and(fn (): SelfBillingAuthorization => requestSelfBilling())->toThrow(function (TransportException $exception) use ($message): void {
            expect($exception->failure)->toBe(TransportFailure::Rejected)
                ->and($exception->messages)->toBe([$message]);
        })
        ->and(fn (): SelfBillingAuthorization => requestSelfBilling())->toThrow(DefinitionException::class)
        ->and($fake->selfBillingRequests())->toHaveCount(3)
        ->and($fake->selfBillingRequests()[0]->taxId)->toBe(S::SELLER);
});

it('prepares the authorized document with the number, led, series and iud received without reserving a number', function (): void {
    $store         = P::configure();
    $authorization = S::iud(['issueDate' => '2026-10-02', 'emitterTaxId' => '100200300', 'ledCode' => 1]);
    $document      = P::invoice(['serie' => 'AUTOFATURA', 'ledCode' => 1, 'documentNumber' => 42, 'selfBilling' => DocumentPayloads::selfBilling()]);

    $prepared = resolve(PrepareDocumentAction::class)->handle($document, iud: $authorization);

    expect($prepared->allocated)->toBeFalse()
        ->and($prepared->iud)->toBe($authorization)
        ->and($store->current(SequenceFixtures::scope()))->toBe(0)
        ->and($prepared->unsignedXml)->toContain('<SelfBilling><AuthorizationId>' . DocumentPayloads::selfBilling()['authorizationId'])
        ->and($prepared->unsignedXml)->toContain('<Serie>AUTOFATURA</Serie>');
});
