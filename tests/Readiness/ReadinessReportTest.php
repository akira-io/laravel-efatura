<?php

declare(strict_types=1);

use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Readiness\ReadinessReport;
use Akira\Efatura\Readiness\ReadinessResult;

it('is ready only without failures and without a mandatory check left out', function (array $results, bool $ready): void {
    expect(new ReadinessReport($results)->ready())->toBe($ready);
})->with([
    'nothing checked'           => [[], false],
    'every check passed'        => [[ReadinessResult::passed(ReadinessCheck::Configuration, 'readiness.configured')], true],
    'a warning'                 => [[ReadinessResult::warning(ReadinessCheck::Clock, 'readiness.clock_skew')], true],
    'an optional check skipped' => [[ReadinessResult::passed(ReadinessCheck::Sequences, 'x'), ReadinessResult::skipped(ReadinessCheck::Endpoint, false)], true],
    'a mandatory check skipped' => [[ReadinessResult::passed(ReadinessCheck::Sequences, 'x'), ReadinessResult::skipped(ReadinessCheck::Certificate, true)], false],
    'a failure'                 => [[ReadinessResult::passed(ReadinessCheck::Sequences, 'x'), ReadinessResult::failed(ReadinessCheck::Certificate, 'certificate.expired')], false],
]);

it('selects the results of one check and the warnings', function (): void {
    $warning = ReadinessResult::warning(ReadinessCheck::Certificate, 'readiness.certificate_expiring', 'efatura.certificates.certificate_path', ['days_left' => 3]);
    $passed  = ReadinessResult::passed(ReadinessCheck::Sequences, 'readiness.sequences_table_present');
    $report  = new ReadinessReport([$passed, $warning]);

    expect($report->of(ReadinessCheck::Certificate))->toBe([$warning])
        ->and($report->of(ReadinessCheck::Clock))->toBe([])
        ->and($report->warnings())->toBe([$warning]);
});

it('keeps only the error code and field of a failure', function (): void {
    $failure = ReadinessResult::failure(ReadinessCheck::Endpoint, TransportException::because(TransportFailure::ConnectionFailed, 'secret-subject'));
    $foreign = ReadinessResult::failure(ReadinessCheck::Endpoint, new RuntimeException('dsn with password'));

    expect([$failure->status, $failure->code, $failure->field, $failure->context])->toBe([ReadinessStatus::Failed, 'transport.connection_failed', null, []])
        ->and([$foreign->code, $foreign->context])->toBe(['readiness.unexpected_failure', []])
        ->and(json_encode(new ReadinessReport([$failure, $foreign])))->not->toContain('secret-subject')->not->toContain('password');
});

it('serializes to the check, status, code, field, context and requirement of each result', function (): void {
    expect(json_encode(new ReadinessReport([ReadinessResult::skipped(ReadinessCheck::Clock, false)])))
        ->toBe('{"results":[{"check":"clock","status":"skipped","code":"readiness.skipped","field":null,"context":[],"required":false}]}');
});
