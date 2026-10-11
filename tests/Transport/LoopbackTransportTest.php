<?php

declare(strict_types=1);

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Tests\Support\LoopbackServer;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\TransportExchange;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

function loopbackFailure(Closure $send): TransportFailure
{
    try {
        $send();
    } catch (TransportException $transportException) {
        return $transportException->failure;
    }

    throw new RuntimeException('The loopback exchange did not fail.');
}

beforeEach(function (): void {
    Http::allowStrayRequests(['http://127.0.0.1:*']);
    $this->exchange = resolve(TransportExchange::class);
});

it('reports an unknown outcome when the platform reads the whole submission and then goes silent or hangs up', function (string $mode): void {
    $server = LoopbackServer::start($mode);

    try {
        $failure = loopbackFailure(fn (): Response => $this->exchange->post($server->config(), T::archive(), []));

        expect($failure)->toBe(TransportFailure::OutcomeUnknown)
            ->and($server->receivedBytes())->toBeGreaterThan(0);
    } finally {
        $server->stop();
    }
})->with(['silent' => 'stall', 'hanging up' => 'close']);

it('reports a submission that could not reach the platform as undelivered', function (): void {
    $failure = loopbackFailure(fn (): Response => $this->exchange->post(LoopbackServer::refusing(), T::archive(), []));

    expect($failure)->toBe(TransportFailure::ConnectionFailed);
});

it('stops keeping a response body past the response limit', function (): void {
    $server = LoopbackServer::start('oversized');

    try {
        $response = $this->exchange->post($server->config(), T::archive(), []);

        expect(fn (): mixed => $this->exchange->submission(T::archive(), $response))
            ->toThrow(fn (TransportException $exception): mixed => expect($exception->failure)->toBe(TransportFailure::ResponseTooLarge))
            ->and($response->toPsrResponse()->getBody()->getSize())->toBeLessThanOrEqual(5 * 1024 * 1024 + 1);
    } finally {
        $server->stop();
    }
});
