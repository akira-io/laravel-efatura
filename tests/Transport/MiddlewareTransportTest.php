<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Akira\Efatura\Tests\Support\IdentifierFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\DocumentLookup;
use Akira\Efatura\Transport\MiddlewareTransport;
use Akira\Efatura\Transport\ReadRetries;
use Akira\Efatura\Transport\SubmissionResponseParser;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

function middlewareTransport(?string $key): MiddlewareTransport
{
    app()->instance(EfaturaConfig::class, ConfigFixtures::load([
        'transmitter' => ['middleware_key' => $key],
        'http'        => ['retry_delay_milliseconds' => 0],
    ]));

    return resolve(MiddlewareTransport::class);
}

function middlewareDfe(string $iud): string
{
    return '<Dfe xmlns="urn:cv:efatura:xsd:v1.0" Id="' . $iud . '"/>';
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-08T10:15:30Z');
    $this->key       = 'key-' . bin2hex(random_bytes(30));
    $this->transport = middlewareTransport($this->key);
});

it('implements the transport contract', function (): void {
    expect($this->transport)->toBeInstanceOf(Transport::class);
});

it('posts the archive as the file part of a multipart request with the transmitter key', function (PackageKind $kind, string $identifier): void {
    Http::fake(['*' => Http::response(str_replace(T::FIRST_IUD, $identifier, T::response('accepted')))]);
    $archive = T::archive([$identifier], $kind);

    $this->transport->submit($archive);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://localhost:3443/v1/' . $kind->value
        && $request->isMultipart()
        && count($request->data()) === 1
        && Arr::sortRecursive($request->data()[0]) === [
            'contents' => $archive->bytes,
            'filename' => $kind->value . '.zip',
            'headers'  => ['Content-Type' => 'application/octet-stream'],
            'name'     => 'file',
        ]
        && $request->header('cv-ef-mw-core-transmitter-key') === [$this->key]
        && $request->header('cv-ef-repository-code') === ['3']
        && $request->header('Accept') === ['application/xml']
        && ! $request->hasHeader('Authorization'));
})->with([
    'documents' => [PackageKind::Documents, T::FIRST_IUD],
    'events'    => [PackageKind::Events, IdentifierFixtures::OFFICIAL_EVENT_ID],
]);

it('returns the result the parser reads from the response', function (string $fixture, int $status): void {
    Http::fake(['*' => Http::response(T::response($fixture), $status)]);

    $result = $this->transport->submit(T::archive());

    expect($result)->toEqual(resolve(SubmissionResponseParser::class)->parse(T::archive(), $status, T::response($fixture)))
        ->and($result->status)->toBe($status);
})->with([
    'accepted'                   => ['accepted', 200],
    'rejected'                   => ['rejected', 200],
    'rejected with a 4xx status' => ['rejected', 422],
]);

it('maps a status without a readable response to a transport failure', function (int $status, TransportFailure $failure): void {
    Http::fake(['*' => Http::response('<html>proxy hostile-marker</html>', $status, ['Location' => 'https://elsewhere.example.test/dfe'])]);

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive()))->toThrow(function (TransportException $exception) use ($status, $failure): void {
        expect($exception->failure)->toBe($failure)
            ->and($exception->retryable)->toBe($failure === TransportFailure::RateLimited)
            ->and($exception->context)->toBe(['status' => $status, 'kind' => 'dfe', 'identifiers' => T::FIRST_IUD, 'bytes' => 33])
            ->and($exception->getMessage())->toBe($failure->value);
    });
    Http::assertSentCount(1);
})->with([
    [401, TransportFailure::Unauthorized],
    [403, TransportFailure::Unauthorized],
    [404, TransportFailure::HttpError],
    [405, TransportFailure::HttpError],
    [413, TransportFailure::PayloadTooLarge],
    [415, TransportFailure::HttpError],
    [429, TransportFailure::RateLimited],
    [500, TransportFailure::OutcomeUnknown],
    [502, TransportFailure::OutcomeUnknown],
    [503, TransportFailure::OutcomeUnknown],
    [504, TransportFailure::OutcomeUnknown],
    [302, TransportFailure::OutcomeUnknown],
]);

it('refuses an unreadable body of a successful response as unexpected', function (): void {
    Http::fake(['*' => Http::response('<html>proxy</html>')]);

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive()))
        ->toThrow(function (TransportException $exception): void {
            expect($exception->failure)->toBe(TransportFailure::UnexpectedResponse);
        });
});

it('never retries a submission', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 503)->push(T::response('accepted'))]);

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive()))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::OutcomeUnknown)
            ->and($exception->context['identifiers'])->toBe(T::FIRST_IUD);
    });
    Http::assertSentCount(1);
});

it('classifies a submission that could not connect as undelivered and retryable', function (): void {
    Http::fake(fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('cURL error 7', $request->toPsrRequest())));

    expect(fn (): SubmissionResult => $this->transport->submit(T::archive()))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::ConnectionFailed)
            ->and($exception->retryable)->toBeTrue();
    });
    Http::assertSentCount(1);
});

it('looks a document up by its iud in the repository the iud names', function (): void {
    Http::fake(['*' => Http::response(middlewareDfe(T::FIRST_IUD))]);

    expect($this->transport->lookupDocument(T::FIRST_IUD))->toEqual(new DocumentLookup(T::FIRST_IUD, true, middlewareDfe(T::FIRST_IUD)));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://localhost:3443/v1/dfe/xml/' . T::FIRST_IUD
        && $request->header('cv-ef-mw-core-transmitter-key') === [$this->key]
        && $request->header('cv-ef-repository-code') === ['3']
        && ! $request->hasHeader('Authorization'));
});

it('finds a document signed with an internally detached signature', function (): void {
    $detached = '<internally-detached>' . middlewareDfe(T::FIRST_IUD) . '</internally-detached>';
    Http::fake(['*' => Http::response($detached)]);

    expect($this->transport->lookupDocument(T::FIRST_IUD)->found)->toBeTrue();
});

it('retries a lookup the platform could not serve and then finds the document', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 503)->push('', 503)->push(middlewareDfe(T::FIRST_IUD))]);

    expect($this->transport->lookupDocument(T::FIRST_IUD)->found)->toBeTrue();
    Http::assertSentCount(3);
});

it('retries a lookup that never reached the middleware', function (): void {
    $attempts = 0;
    Http::fake(function (Request $request) use (&$attempts): PromiseInterface {
        $attempts++;

        return $attempts < 3
            ? Create::rejectionFor(new ConnectException('cURL error 7', $request->toPsrRequest()))
            : Http::response(middlewareDfe(T::FIRST_IUD));
    });

    expect($this->transport->lookupDocument(T::FIRST_IUD)->found)->toBeTrue()
        ->and($attempts)->toBe(3);
});

it('stops retrying a lookup after the configured retries', function (): void {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn (): DocumentLookup => $this->transport->lookupDocument(T::FIRST_IUD))
        ->toThrow(function (TransportException $exception): void {
            expect($exception->failure)->toBe(TransportFailure::HttpError);
        });
    Http::assertSentCount(3);
});

it('does not retry a lookup whose connection broke after the request left', function (): void {
    Http::fake(fn (Request $request): PromiseInterface => Create::rejectionFor(new RequestException('cURL error 56', $request->toPsrRequest())));

    expect(fn (): DocumentLookup => $this->transport->lookupDocument(T::FIRST_IUD))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::OutcomeUnknown)
            ->and($exception->context)->toBe(['identifiers' => T::FIRST_IUD]);
    });
    Http::assertSentCount(1);
});

it('reports a document the platform does not hold as not found without retrying', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    expect($this->transport->lookupDocument(T::FIRST_IUD))->toEqual(new DocumentLookup(T::FIRST_IUD, false, null));
    Http::assertSentCount(1);
});

it('refuses a lookup response that does not hold the document asked for', function (int $status, string $body): void {
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn (): DocumentLookup => $this->transport->lookupDocument(T::FIRST_IUD))->toThrow(function (TransportException $exception) use ($body): void {
        expect($exception->failure)->toBe(TransportFailure::UnexpectedResponse)
            ->and($exception->context['identifiers'])->toBe(T::FIRST_IUD)
            ->and($exception->getMessage())->not->toContain($body === '' ? T::SECOND_IUD : $body);
    });
})->with([
    'another document'   => [200, middlewareDfe(T::SECOND_IUD)],
    'a foreign root'     => [200, '<Event xmlns="urn:cv:efatura:xsd:v1.0" Id="' . T::FIRST_IUD . '"/>'],
    'an unqualified dfe' => [200, '<Dfe Id="' . T::FIRST_IUD . '"/>'],
    'proxy html'         => [200, '<html>hostile</html>'],
    'an empty body'      => [200, ''],
    'no content'         => [204, ''],
]);

it('maps a failed lookup to a transport failure', function (int $status, TransportFailure $failure): void {
    Http::fake(['*' => Http::response('', $status)]);

    expect(fn (): DocumentLookup => $this->transport->lookupDocument(T::FIRST_IUD))->toThrow(function (TransportException $exception) use ($status, $failure): void {
        expect($exception->failure)->toBe($failure)
            ->and($exception->context)->toBe(['status' => $status, 'identifiers' => T::FIRST_IUD]);
    });
})->with([
    [401, TransportFailure::Unauthorized],
    [429, TransportFailure::RateLimited],
    [500, TransportFailure::HttpError],
    [302, TransportFailure::HttpError],
]);

it('validates the iud before it reaches the path', function (string $iud): void {
    Http::fake();

    expect(fn (): DocumentLookup => $this->transport->lookupDocument($iud))->toFailValidationOn('iud', 'The iud must be an official IUD with a valid check digit.');
    Http::assertNothingSent();
})->with([
    'a traversal'         => ['../' . T::FIRST_IUD],
    'a wrong check digit' => [substr(T::FIRST_IUD, 0, -1) . '0'],
]);

it('refuses to send anything without the transmitter key', function (Closure $operation, string $identifiers): void {
    Http::fake();

    expect(fn (): mixed => $operation(middlewareTransport(null)))->toThrow(function (TransportException $exception) use ($identifiers): void {
        expect($exception->failure)->toBe(TransportFailure::MissingCredentials)
            ->and($exception->field)->toBe('efatura.transmitter.middleware_key')
            ->and($exception->context['identifiers'])->toBe($identifiers);
    });
    Http::assertNothingSent();
})->with([
    'a submission' => [fn (MiddlewareTransport $transport): SubmissionResult => $transport->submit(T::archive()), T::FIRST_IUD],
    'a lookup'     => [fn (MiddlewareTransport $transport): DocumentLookup => $transport->lookupDocument(T::FIRST_IUD), T::FIRST_IUD],
]);

it('keeps the transmitter key out of every failure', function (Closure $response): void {
    $original = ini_set('zend.exception_ignore_args', '0');
    Http::fake($response);

    try {
        $this->transport->submit(T::archive());
    } catch (TransportException $transportException) {
        expect(ExceptionTrace::exposed($transportException))->not->toContain($this->key);
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }

    expect($transportException ?? null)->toBeInstanceOf(TransportException::class);
})->with([
    'an unknown outcome'   => [fn (): PromiseInterface => Http::response('', 503)],
    'an unexpected body'   => [fn (): PromiseInterface => Http::response('<html/>')],
    'a refused connection' => [fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('cURL error 7', $request->toPsrRequest()))],
]);

it('retries only failures that left nothing behind', function (?Throwable $exception, bool $retryable): void {
    expect(resolve(ReadRetries::class)->retryable($exception))->toBe($retryable);
})->with([
    'any other failure'               => [new RuntimeException('unrelated'), false],
    'a redirect, which is no failure' => [null, false],
]);
