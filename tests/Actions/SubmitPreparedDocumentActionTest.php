<?php

declare(strict_types=1);

use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Actions\SubmitPreparedDocumentAction;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\RemoteMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->store    = P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    $this->prepared = resolve(PrepareDocumentAction::class)->handle(P::invoice());
    $this->fake     = resolve(FakeTransport::class);
});

it('sends the prepared archive and reads the result of its document', function (): void {
    app()->instance(Transport::class, $this->fake);

    $issued = resolve(SubmitPreparedDocumentAction::class)->handle($this->prepared);

    expect($issued->prepared)->toBe($this->prepared)
        ->and($issued->authorized())->toBeTrue()
        ->and($issued->result)->toBe($issued->submission->entry($this->prepared->iud))
        ->and($this->fake->submitted())->toBe([$this->prepared->archive]);
});

it('reports a rejected document as not authorized', function (): void {
    app()->instance(Transport::class, $this->fake->reject($this->prepared->iud, new RemoteMessage('BIZ', 'ERROR', null, 'BIZ-001', null, null)));

    $issued = resolve(SubmitPreparedDocumentAction::class)->handle($this->prepared);

    expect($issued->authorized())->toBeFalse()
        ->and($issued->result->messages[0]->code)->toBe('BIZ-001');
});

it('submits through the http transport', function (string $fixture, bool $authorized): void {
    Http::fake(['*' => Http::response(str_replace(T::FIRST_IUD, $this->prepared->iud, T::response($fixture)))]);

    expect(resolve(SubmitPreparedDocumentAction::class)->handle($this->prepared)->authorized())->toBe($authorized);
})->with([
    'accepted' => ['accepted', true],
    'rejected' => ['rejected', false],
]);

it('resends the same archive after an unknown outcome without numbering again', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 504)->push(str_replace(T::FIRST_IUD, $this->prepared->iud, T::response('accepted')))]);
    $submit = resolve(SubmitPreparedDocumentAction::class);

    expect(fn (): IssuedDocument => $submit->handle($this->prepared))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::OutcomeUnknown)
            ->and($exception->context['identifiers'])->toBe($this->prepared->iud);
    });

    $issued = $submit->handle($this->prepared);
    $sent   = Http::recorded()->map(fn (array $pair): string => $pair[0]->data()[0]['contents'])->all();

    expect($issued->authorized())->toBeTrue()
        ->and($sent)->toBe([$this->prepared->archive->bytes, $this->prepared->archive->bytes])
        ->and($this->store->current(SequenceFixtures::scope()))->toBe(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://localhost:3443/v1/dfe');
});
