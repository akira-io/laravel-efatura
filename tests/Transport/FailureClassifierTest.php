<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\CurlFailure;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\FailureClassifier;
use Akira\Efatura\Transport\TransportRequests;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

function connectionFailure(Closure $cause, string $credential = 'unused-credential'): ConnectionException
{
    Http::fake(fn (Request $request): PromiseInterface => Create::rejectionFor($cause($request->toPsrRequest())));

    try {
        resolve(TransportRequests::class)->for(ConfigFixtures::load()->http->middleware, Environment::Test)
            ->withHeaders(['cv-ef-mw-core-transmitter-key' => $credential])
            ->post('dfe');
    } catch (ConnectionException $connectionException) {
        return $connectionException;
    }

    throw new RuntimeException('The faked connection did not fail.');
}

it('reads the curl failure a guzzle 7 handler reports to decide whether the request left', function (array $context, TransportFailure $failure): void {
    $exception = resolve(FailureClassifier::class)->classify(new ConnectionException('cURL error', 0, new CurlFailure($context)), T::archive());

    expect($exception->failure)->toBe($failure)
        ->and($exception->retryable)->toBe($failure === TransportFailure::ConnectionFailed)
        ->and($exception->context)->toBe(['kind' => 'dfe', 'identifiers' => T::FIRST_IUD])
        ->and($exception->getPrevious())->toBeNull();
})->with([
    'host not resolved'          => [['errno' => 6], TransportFailure::ConnectionFailed],
    'connection refused'         => [['errno' => 7], TransportFailure::ConnectionFailed],
    'connect timeout'            => [['errno' => 28, 'connect_time' => 0], TransportFailure::ConnectionFailed],
    'connect timeout as a float' => [['errno' => 28, 'connect_time' => 0.0], TransportFailure::ConnectionFailed],
    'tls handshake'              => [['errno' => 35, 'connect_time' => 0.02], TransportFailure::ConnectionFailed],
    'untrusted certificate'      => [['errno' => 60], TransportFailure::ConnectionFailed],
    'timeout after connecting'   => [['errno' => 28, 'connect_time' => 0.12], TransportFailure::OutcomeUnknown],
    'timeout with no timing'     => [['errno' => 28], TransportFailure::OutcomeUnknown],
    'timeout with a text timing' => [['errno' => 28, 'connect_time' => '0'], TransportFailure::OutcomeUnknown],
    'empty reply'                => [['errno' => 52], TransportFailure::OutcomeUnknown],
    'receive failure'            => [['errno' => 56], TransportFailure::OutcomeUnknown],
    'unknown error'              => [['errno' => 999], TransportFailure::OutcomeUnknown],
]);

it('falls back on the kind of failure when the handler reports no curl error, as guzzle 8 does', function (Closure $cause, TransportFailure $failure): void {
    expect(resolve(FailureClassifier::class)->classify(connectionFailure($cause), T::FIRST_IUD)->failure)->toBe($failure);
})->with([
    'a connect failure' => [fn (RequestInterface $request): ConnectException => new ConnectException('cURL error 7', $request), TransportFailure::ConnectionFailed],
    'any other failure' => [fn (RequestInterface $request): RequestException => new RequestException('cURL error 56', $request), TransportFailure::OutcomeUnknown],
]);

it('treats a connection failure without a cause as an unknown outcome', function (): void {
    expect(resolve(FailureClassifier::class)->classify(new ConnectionException('lost'), T::FIRST_IUD)->failure)->toBe(TransportFailure::OutcomeUnknown);
});

it('keeps the request and its credentials out of the transport failure', function (): void {
    $credential = 'credential-' . bin2hex(random_bytes(8));
    $original   = ini_set('zend.exception_ignore_args', '0');

    try {
        $connection = connectionFailure(fn (RequestInterface $request): ConnectException => new ConnectException('cURL error 7', $request), $credential);
        $exception  = resolve(FailureClassifier::class)->classify($connection, T::archive());

        expect(ExceptionTrace::exposed($connection))->toContain($credential)
            ->and(ExceptionTrace::exposed($exception))->not->toContain($credential)
            ->and($exception)->toBeInstanceOf(TransportException::class);
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }
});
