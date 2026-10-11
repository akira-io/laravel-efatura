<?php

declare(strict_types=1);

use Akira\Efatura\Actions\IssueDocumentAction;
use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Events\DocumentAuthorized;
use Akira\Efatura\Events\DocumentPrepared;
use Akira\Efatura\Events\DocumentRejected;
use Akira\Efatura\Events\DocumentSubmissionFailed;
use Akira\Efatura\Exceptions\IssuanceException;
use Akira\Efatura\Exceptions\OAuthException;
use Akira\Efatura\Exceptions\SequenceException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\OAuth\RefreshTokenAccessTokenProvider;
use Akira\Efatura\Sequence\DatabaseSequenceStore;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\InMemoryRefreshTokens;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->store = P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    $this->trail = new ArrayObject;
    Event::listen(DocumentPrepared::class, function (DocumentPrepared $event): void {
        $this->trail[] = 'prepared';
        $this->iud     = $event->prepared->iud;
    });
});

function answerIssuedDocument(object $test, string $fixture): void
{
    Http::fake(function () use ($test, $fixture): PromiseInterface {
        $test->trail[] = 'request';

        return Http::response(str_replace(T::FIRST_IUD, $test->iud, T::response($fixture)));
    });
}

it('announces the prepared document, submits it and announces the response', function (string $fixture, string $event, bool $authorized): void {
    answerIssuedDocument($this, $fixture);
    Event::listen([DocumentAuthorized::class, DocumentRejected::class], function (DocumentAuthorized|DocumentRejected $announced): void {
        $this->trail[] = $announced::class;
        $this->result  = $announced->result;
    });

    $issued = resolve(IssueDocumentAction::class)->handle(P::invoice());

    expect($this->trail->getArrayCopy())->toBe(['prepared', 'request', $event])
        ->and($issued->authorized())->toBe($authorized)
        ->and($issued->prepared->iud)->toBe($this->iud)
        ->and($this->result)->toBe($issued->result)
        ->and(count($issued->result->messages))->toBe($authorized ? 0 : 2);
})->with([
    'authorized' => ['accepted', DocumentAuthorized::class, true],
    'rejected'   => ['rejected', DocumentRejected::class, false],
]);

it('sends nothing when a listener of the prepared document fails', function (): void {
    Http::fake();
    Event::listen(DocumentPrepared::class, fn (): never => throw new RuntimeException('storage unavailable'));

    expect(fn (): IssuedDocument => resolve(IssueDocumentAction::class)->handle(P::invoice()))->toThrow(function (IssuanceException $exception): void {
        expect($exception->errorCode)->toBe('issuance.aborted_before_submission')
            ->and($exception->getMessage())->toBe('issuance.aborted_before_submission')
            ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($exception->context['iud'])->toBe($this->iud)
            ->and($exception->context['documentNumber'])->toBe(1)
            ->and(resolve(ParseIudAction::class)->handle($this->iud)->documentNumber)->toBe(1);
    });
    Http::assertNothingSent();
});

it('returns the authorized document when a listener of the response fails', function (): void {
    Exceptions::fake();
    answerIssuedDocument($this, 'accepted');
    Event::listen(DocumentAuthorized::class, fn (): never => throw new RuntimeException('mailer unavailable'));

    expect(resolve(IssueDocumentAction::class)->handle(P::invoice())->authorized())->toBeTrue();
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'mailer unavailable');
});

it('announces an unknown outcome and lets it through without numbering again', function (): void {
    Exceptions::fake();
    Http::fake(['*' => Http::response('', 503)]);
    Event::listen(DocumentSubmissionFailed::class, function (DocumentSubmissionFailed $event): never {
        $this->failed = $event;

        throw new RuntimeException('listener failed');
    });

    expect(fn (): IssuedDocument => resolve(IssueDocumentAction::class)->handle(P::invoice()))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::OutcomeUnknown)
            ->and($exception->context['identifiers'])->toBe($this->iud);
    })
        ->and($this->failed->prepared->iud)->toBe($this->iud)
        ->and($this->failed->errorCode)->toBe('transport.outcome_unknown')
        ->and($this->failed->retryable)->toBeFalse()
        ->and($this->failed->mayHaveBeenProcessed)->toBeTrue()
        ->and($this->store->current(SequenceFixtures::scope()))->toBe(1);
    Exceptions::assertReported(RuntimeException::class);
    Http::assertSentCount(1);
});

it('announces a token failure of the platform driver as a submission that was never processed', function (): void {
    Event::fake([DocumentSubmissionFailed::class]);
    Http::fake();
    P::configure(config: ['efatura.driver' => 'platform', 'app.key' => 'base64:' . base64_encode(random_bytes(32))]);
    app()->instance(AccessTokenProvider::class, resolve(RefreshTokenAccessTokenProvider::class, ['refreshTokens' => new InMemoryRefreshTokens]));

    expect(fn (): IssuedDocument => resolve(IssueDocumentAction::class)->handle(P::invoice()))
        ->toThrow(OAuthException::class, 'oauth.refresh_token_missing');
    Event::assertDispatched(
        DocumentSubmissionFailed::class,
        fn (DocumentSubmissionFailed $event): bool => $event->errorCode === 'oauth.refresh_token_missing'
            && ! $event->retryable
            && ! $event->mayHaveBeenProcessed
            && $event->prepared->iud === $this->iud,
    );
    Http::assertNothingSent();
});

it('issues through a transport bound by the application', function (): void {
    Http::fake();
    $fake = resolve(FakeTransport::class);
    app()->instance(Transport::class, $fake);

    $issued = resolve(IssueDocumentAction::class)->handle(P::invoice());

    expect($issued->authorized())->toBeTrue()
        ->and($fake->submitted())->toBe([$issued->prepared->archive]);
    Http::assertNothingSent();
});

it('reserves no number and sends nothing inside a transaction of the sequence connection', function (): void {
    Http::fake();
    app()->instance(SequenceStore::class, resolve(DatabaseSequenceStore::class));
    SequenceFixtures::migrate();

    expect(fn (): IssuedDocument => DB::transaction(fn (): IssuedDocument => resolve(IssueDocumentAction::class)->handle(P::invoice())))
        ->toThrow(SequenceException::class, 'sequence.inside_transaction')
        ->and($this->trail->getArrayCopy())->toBe([]);
    Http::assertNothingSent();
});
