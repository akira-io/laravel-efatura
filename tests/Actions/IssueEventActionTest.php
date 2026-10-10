<?php

declare(strict_types=1);

use Akira\Efatura\Actions\IssueEventAction;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Events\EventAuthorized;
use Akira\Efatura\Events\EventPrepared;
use Akira\Efatura\Events\EventRejected;
use Akira\Efatura\Events\EventSubmissionFailed;
use Akira\Efatura\Exceptions\IssuanceException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Issuance\IssuedEvent;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\RemoteMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    $this->eventId = 'CV3261002120000' . P::TRANSMITTER;
    $this->fake    = resolve(FakeTransport::class);
});

it('issues a cancellation through the http transport with the event id as the entry', function (): void {
    Event::fake([EventAuthorized::class]);
    Http::fake(['*' => Http::response(str_replace(T::FIRST_IUD, $this->eventId, T::response('accepted')))]);

    $issued = resolve(IssueEventAction::class)->handle(P::event());

    expect($issued->authorized())->toBeTrue()
        ->and($issued->result->entryName)->toBe($this->eventId . '.xml');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://localhost:3443/v1/event');
    Event::assertDispatched(EventAuthorized::class, fn (EventAuthorized $event): bool => $event->result === $issued->result && $event->prepared === $issued->prepared);
});

it('announces the outcome the fake transport programs', function (Closure $program, string $announced, bool $authorized): void {
    Event::fake([EventPrepared::class, EventAuthorized::class, EventRejected::class]);
    app()->instance(Transport::class, $program($this->fake, $this->eventId));

    $issued = resolve(IssueEventAction::class)->handle(P::event());

    expect($issued->authorized())->toBe($authorized)
        ->and($this->fake->submitted())->toBe([$issued->prepared->archive]);
    Event::assertDispatched(EventPrepared::class);
    Event::assertDispatched($announced);
})->with([
    'accepted' => [fn (FakeTransport $fake, string $id): FakeTransport => $fake->accept($id), EventAuthorized::class, true],
    'rejected' => [
        fn (FakeTransport $fake, string $id): FakeTransport => $fake->reject($id, new RemoteMessage('BIZ', 'ERROR', null, 'BIZ-404', null, null)),
        EventRejected::class,
        false,
    ],
]);

it('sends nothing when a listener of the prepared event fails', function (): void {
    app()->instance(Transport::class, $this->fake);
    Event::listen(EventPrepared::class, fn (): never => throw new RuntimeException('storage unavailable'));

    expect(fn (): IssuedEvent => resolve(IssueEventAction::class)->handle(P::event()))->toThrow(function (IssuanceException $exception): void {
        expect($exception->errorCode)->toBe('issuance.aborted_before_submission')
            ->and($exception->context)->toBe(['eventId' => $this->eventId, 'eventTypeCode' => 'FDC'])
            ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class);
    })->and($this->fake->submitted())->toBe([]);
});

it('announces a delivery failure of the event and lets it through', function (): void {
    Event::fake([EventSubmissionFailed::class]);
    app()->instance(Transport::class, $this->fake->fail($this->eventId, TransportFailure::ConnectionFailed));

    expect(fn (): IssuedEvent => resolve(IssueEventAction::class)->handle(P::event()))->toThrow(TransportException::class, 'transport.connection_failed');
    Event::assertDispatched(
        EventSubmissionFailed::class,
        fn (EventSubmissionFailed $event): bool => $event->failure === TransportFailure::ConnectionFailed && $event->retryable && $event->prepared->eventId === $this->eventId,
    );
});
