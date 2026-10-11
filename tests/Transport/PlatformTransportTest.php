<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Akira\Efatura\Tests\Support\IdentifierFixtures;
use Akira\Efatura\Tests\Support\OAuthFixtures as O;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\DocumentLookup;
use Akira\Efatura\Transport\PlatformTransport;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function platformRequests(): array
{
    return Http::recorded()->map(fn (array $pair): Request => $pair[0])->values()->all();
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-08T10:15:30Z');
    $this->tokens = new RecordingAccessTokens(bin2hex(random_bytes(12)));
    app()->instance(EfaturaConfig::class, ConfigFixtures::load([
        'driver'      => 'platform',
        'transmitter' => ['middleware_key' => str_repeat('m', Fiscal::TRANSMITTER_KEY_LENGTH)],
        'http'        => ['retry_delay_milliseconds' => 0],
    ]));
    app()->instance(AccessTokenProvider::class, $this->tokens);

    $this->transport = resolve(PlatformTransport::class);
});

it('implements the transport contract', function (): void {
    expect($this->transport)->toBeInstanceOf(Transport::class);
});

it('posts the archive to the platform with the access token of its emitter', function (PackageKind $kind, string $identifier, string $scope): void {
    Http::fake(['*' => Http::response(str_replace(T::FIRST_IUD, $identifier, T::response('accepted')))]);
    $archive = T::archive([$identifier], $kind, emitters: [O::EMITTER]);
    $token   = $this->tokens->current();

    $this->transport->submit($archive);

    expect($this->tokens->calls)->toBe([['token', O::EMITTER, [$scope]]]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://services.efatura.cv/v1/' . $kind->value
        && $request->isMultipart()
        && Arr::sortRecursive($request->data()[0]) === [
            'contents' => $archive->bytes,
            'filename' => $kind->value . '.zip',
            'headers'  => ['Content-Type' => 'application/octet-stream'],
            'name'     => 'file',
        ]
        && $request->header('Authorization') === ['Bearer ' . $token]
        && $request->header('cv-ef-repository-code') === ['3']
        && $request->header('Accept') === ['application/xml']
        && ! $request->hasHeader(Fiscal::TRANSMITTER_KEY_HEADER));
})->with([
    'documents' => [PackageKind::Documents, T::FIRST_IUD, 'cv_ef_dfe_create'],
    'events'    => [PackageKind::Events, IdentifierFixtures::OFFICIAL_EVENT_ID, 'cv_ef_event_create'],
]);

it('resends once with a renewed token after the platform refuses the first one', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 401)->push(T::response('accepted'))]);
    $first = $this->tokens->current();

    $result = $this->transport->submit(T::archive());
    $sent   = platformRequests();

    expect($result->entry(T::FIRST_IUD)->succeeded)->toBeTrue()
        ->and($sent)->toHaveCount(2)
        ->and($sent[0]->header('Authorization'))->toBe(['Bearer ' . $first])
        ->and($sent[1]->header('Authorization'))->toBe(['Bearer ' . $this->tokens->current()])
        ->and($this->tokens->current())->not->toBe($first)
        ->and(array_column($this->tokens->calls, 0))->toBe(['token', 'forget', 'token']);
});

it('gives up after the renewed token is refused as well', function (): void {
    Http::fake(['*' => Http::response('', 401)]);

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive()))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::Unauthorized)
            ->and($exception->context['status'])->toBe(401);
    });
    Http::assertSentCount(2);
});

it('does not resend a submission the platform forbids', function (): void {
    Http::fake(['*' => Http::response('', 403)]);

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive()))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::Unauthorized);
    });
    Http::assertSentCount(1);
    expect(array_column($this->tokens->calls, 0))->toBe(['token']);
});

it('refuses an archive of more than one emitter before asking for any token', function (array $emitters): void {
    Http::fake();

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive([T::FIRST_IUD, T::SECOND_IUD], emitters: $emitters)))
        ->toThrow(function (TransportException $exception): void {
            expect($exception->failure)->toBe(TransportFailure::MixedEmitters)
                ->and($exception->retryable)->toBeFalse()
                ->and($exception->context['identifiers'])->toBe(T::FIRST_IUD . ',' . T::SECOND_IUD);
        });
    Http::assertNothingSent();
    expect($this->tokens->calls)->toBe([]);
})->with([
    'two emitters' => [[O::EMITTER, '200300400']],
    'no emitter'   => [[]],
]);

it('looks a document up with the access token of the emitter its iud names', function (): void {
    Http::fake(['*' => Http::response('<Dfe xmlns="urn:cv:efatura:xsd:v1.0" Id="' . T::FIRST_IUD . '"/>')]);

    expect($this->transport->lookupDocument(T::FIRST_IUD))->toBeInstanceOf(DocumentLookup::class)
        ->found->toBeTrue()
        ->and($this->tokens->calls)->toBe([['token', O::EMITTER, ['cv_ef_dfe_read_iud']]]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://services.efatura.cv/v1/dfe/xml/' . T::FIRST_IUD
        && $request->header('Authorization') === ['Bearer ' . $this->tokens->current()]
        && $request->header('cv-ef-repository-code') === ['3']);
});

it('renews the token of a refused lookup once', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 401)->push('', 404)]);

    expect($this->transport->lookupDocument(T::FIRST_IUD)->found)->toBeFalse()
        ->and(array_column($this->tokens->calls, 0))->toBe(['token', 'forget', 'token']);
    Http::assertSentCount(2);
});

it('sends the token only in the authorization header the host has to hide from its request logs', function (): void {
    $original = ini_set('zend.exception_ignore_args', '0');
    $sending  = [];
    Event::listen(RequestSending::class, function (RequestSending $event) use (&$sending): void {
        $sending[] = $event->request;
    });
    Http::fake(['*' => Http::response('', 503)]);

    try {
        $this->transport->submit(T::archive());
    } catch (TransportException $transportException) {
        expect(ExceptionTrace::exposed($transportException))->not->toContain($this->tokens->current());
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }

    expect($transportException ?? null)->toBeInstanceOf(TransportException::class)
        ->and($transportException->failure)->toBe(TransportFailure::OutcomeUnknown)
        ->and($sending)->toHaveCount(1)
        ->and($sending[0]->header('Authorization'))->toBe(['Bearer ' . $this->tokens->current()]);
});
