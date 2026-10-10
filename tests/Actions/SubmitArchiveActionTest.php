<?php

declare(strict_types=1);

use Akira\Efatura\Actions\SubmitArchiveAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\SubmissionOutcome;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\SubmissionResult;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app()->instance(EfaturaConfig::class, ConfigFixtures::load(['environment' => 'test', 'transmitter' => ['middleware_key' => str_repeat('k', 64)]]));
});

it('submits the archive through the configured transport', function (string $fixture, SubmissionOutcome $outcome): void {
    Http::fake(['*' => Http::response(T::response($fixture))]);

    expect(resolve(SubmitArchiveAction::class)->handle(T::archive())->outcome())->toBe($outcome);
    Http::assertSentCount(1);
})->with([
    'accepted' => ['accepted', SubmissionOutcome::Accepted],
    'rejected' => ['rejected', SubmissionOutcome::Rejected],
]);

it('propagates an unknown outcome with the identifiers of the archive', function (): void {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn (): SubmissionResult => resolve(SubmitArchiveAction::class)->handle(T::archive()))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::OutcomeUnknown)
            ->and($exception->context['identifiers'])->toBe(T::FIRST_IUD);
    });
});

it('refuses an archive of another repository before sending anything', function (Environment $repository): void {
    Http::fake();
    $fake = resolve(FakeTransport::class);
    app()->instance(Transport::class, $fake);

    expect(fn (): SubmissionResult => resolve(SubmitArchiveAction::class)->handle(T::archive(repository: $repository)))
        ->toThrow(function (TransportException $exception): void {
            expect($exception->failure)->toBe(TransportFailure::RepositoryMismatch)
                ->and($exception->field)->toBe('efatura.environment')
                ->and($exception->context)->toBe(['kind' => 'dfe', 'identifiers' => T::FIRST_IUD]);
        })
        ->and($fake->submitted())->toBe([]);
    Http::assertNothingSent();
})->with([Environment::Production, Environment::Homologation]);
