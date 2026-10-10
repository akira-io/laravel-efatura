<?php

declare(strict_types=1);

use Akira\Efatura\Actions\PrepareEventAction;
use Akira\Efatura\Actions\SubmitPreparedEventAction;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Issuance\IssuedEvent;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    $this->prepared = resolve(PrepareEventAction::class)->handle(P::event());
});

it('sends the prepared event archive and reads the result of its event', function (): void {
    $fake = resolve(FakeTransport::class);
    app()->instance(Transport::class, $fake);

    $issued = resolve(SubmitPreparedEventAction::class)->handle($this->prepared);

    expect($issued->prepared)->toBe($this->prepared)
        ->and($issued->authorized())->toBeTrue()
        ->and($issued->result->identifier)->toBe($this->prepared->eventId)
        ->and($issued->submission->entries)->toBe([$issued->result])
        ->and($fake->submitted())->toBe([$this->prepared->archive]);
});

it('submits the event to the event resource of the middleware', function (): void {
    Http::fake(['*' => Http::response(str_replace(T::FIRST_IUD, $this->prepared->eventId, T::response('rejected')))]);

    expect(resolve(SubmitPreparedEventAction::class)->handle($this->prepared)->authorized())->toBeFalse();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://localhost:3443/v1/event');
});

it('propagates a delivery failure of the event', function (): void {
    app()->instance(Transport::class, resolve(FakeTransport::class)->fail($this->prepared->eventId, TransportFailure::ConnectionFailed));

    expect(fn (): IssuedEvent => resolve(SubmitPreparedEventAction::class)->handle($this->prepared))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::ConnectionFailed)
            ->and($exception->retryable)->toBeTrue();
    });
});
