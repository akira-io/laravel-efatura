<?php

declare(strict_types=1);

use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SubmissionOutcome;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;

function submissionOf(bool ...$succeeded): SubmissionResult
{
    $entries = array_map(
        static fn (bool $accepted, int $position): EntryResult => new EntryResult('E' . $position . '.xml', 'E' . $position, $accepted, $accepted ? 'A' : null, null, []),
        $succeeded,
        array_keys($succeeded),
    );

    return new SubmissionResult(PackageKind::Documents, 200, $entries, CarbonImmutable::now());
}

it('classifies a submission by how many entries were authorized', function (array $succeeded, SubmissionOutcome $outcome): void {
    expect(submissionOf(...$succeeded)->outcome())->toBe($outcome);
})->with([
    'all accepted'  => [[true, true], SubmissionOutcome::Accepted],
    'all rejected'  => [[false, false], SubmissionOutcome::Rejected],
    'some accepted' => [[true, false], SubmissionOutcome::Partial],
    'no entries'    => [[], SubmissionOutcome::Rejected],
]);

it('refuses to look up an entry the submission does not hold', function (): void {
    expect(fn (): EntryResult => submissionOf(true)->entry('E9'))
        ->toThrow(DefinitionException::class, 'The submission holds no entry for E9.');
});

it('names the archive or the identifier a transport failure concerns', function (): void {
    expect(TransportException::because(TransportFailure::OutcomeUnknown, T::archive([T::FIRST_IUD, T::SECOND_IUD]), 503)->context)
        ->toBe(['status' => 503, 'kind' => 'dfe', 'identifiers' => T::FIRST_IUD . ',' . T::SECOND_IUD])
        ->and(TransportException::because(TransportFailure::ConnectionFailed, T::FIRST_IUD)->context)->toBe(['identifiers' => T::FIRST_IUD])
        ->and(TransportException::because(TransportFailure::MissingCredentials, field: 'efatura.transmitter.middleware_key')->field)
        ->toBe('efatura.transmitter.middleware_key');
});

it('marks only the failures that left the request undelivered or throttled as retryable', function (TransportFailure $failure): void {
    expect(TransportException::because($failure)->retryable)
        ->toBe(in_array($failure, [TransportFailure::ConnectionFailed, TransportFailure::RateLimited], true))
        ->and(TransportException::because($failure)->errorCode)->toBe($failure->value);
})->with(TransportFailure::cases());
