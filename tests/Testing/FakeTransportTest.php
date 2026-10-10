<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SubmissionOutcome;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\DocumentLookup;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-08T10:15:30Z');
    Http::fake();
    $this->transport = resolve(FakeTransport::class);
});

afterEach(function (): void {
    Http::assertNothingSent();
});

it('implements the transport contract and accepts every entry by default', function (): void {
    $archive = T::archive([T::FIRST_IUD, T::SECOND_IUD]);

    $result = $this->transport->submit($archive);

    expect($this->transport)->toBeInstanceOf(Transport::class)
        ->and($this->transport->submitted())->toBe([$archive])
        ->and($result->kind)->toBe(PackageKind::Documents)
        ->and($result->status)->toBe(200)
        ->and($result->receivedAt->equalTo(CarbonImmutable::now()))->toBeTrue()
        ->and($result->outcome())->toBe(SubmissionOutcome::Accepted)
        ->and($result->entry(T::FIRST_IUD)->entryName)->toBe(T::FIRST_IUD . '.xml')
        ->and($result->entry(T::FIRST_IUD)->authorizationCode)->toBe('FAKE-' . T::FIRST_IUD)
        ->and($result->entry(T::FIRST_IUD)->signatureTimeStamp)->toBe('2026-02-08 09:15:30');
});

it('answers each entry with the outcome programmed for it, once', function (): void {
    $message = new RemoteMessage('BIZ', 'ERROR', null, 'BIZ-001', 'Refused.', null);
    $this->transport->accept(T::FIRST_IUD, 'AUTH-7')->reject(T::SECOND_IUD, $message);
    $archive = T::archive([T::FIRST_IUD, T::SECOND_IUD]);

    $first  = $this->transport->submit($archive);
    $second = $this->transport->submit($archive);

    expect($first->outcome())->toBe(SubmissionOutcome::Partial)
        ->and($first->entry(T::FIRST_IUD)->authorizationCode)->toBe('AUTH-7')
        ->and($first->entry(T::SECOND_IUD)->succeeded)->toBeFalse()
        ->and($first->entry(T::SECOND_IUD)->messages)->toBe([$message])
        ->and($second->outcome())->toBe(SubmissionOutcome::Accepted);
});

it('fails the whole submission the way a transport does', function (TransportFailure $failure): void {
    $this->transport->fail(T::SECOND_IUD, $failure);
    $archive = T::archive([T::FIRST_IUD, T::SECOND_IUD]);

    expect(fn (): SubmissionResult => $this->transport->submit($archive))->toThrow(function (TransportException $exception) use ($failure): void {
        expect($exception->failure)->toBe($failure)
            ->and($exception->retryable)->toBe($failure->retryable())
            ->and($exception->context['identifiers'])->toBe(T::FIRST_IUD . ',' . T::SECOND_IUD);
    })->and($this->transport->submitted())->toBe([$archive])
        ->and($this->transport->submit($archive)->outcome())->toBe(SubmissionOutcome::Accepted);
})->with([TransportFailure::OutcomeUnknown, TransportFailure::ConnectionFailed]);

it('finds only the documents it holds', function (): void {
    $this->transport->holdDocument(T::FIRST_IUD, '<Dfe/>');

    expect($this->transport->lookupDocument(T::FIRST_IUD))->toEqual(new DocumentLookup(T::FIRST_IUD, true, '<Dfe/>'))
        ->and($this->transport->lookupDocument(T::SECOND_IUD))->toEqual(new DocumentLookup(T::SECOND_IUD, false, null))
        ->and($this->transport->lookedUp())->toBe([T::FIRST_IUD, T::SECOND_IUD]);
});
