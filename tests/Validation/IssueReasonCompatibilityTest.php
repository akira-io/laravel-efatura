<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('accepts every issue reason allowed for credit notes', function (string $reason): void {
    expect(CreditNoteData::from(P::correction(['issueReasonCode' => $reason]))->issueReason)->toBe(IssueReason::from($reason));
})->with(['2', '3', '6', '7', '8', '9', 'IN', 'DRP']);

it('rejects every issue reason not allowed for credit notes', function (string $reason): void {
    $payload = P::correction(['issueReasonCode' => $reason]);

    expect(fn (): CreditNoteData => CreditNoteData::from($payload))
        ->toFailValidationOn('issueReasonCode', 'The selected issue reason code is invalid.');
})->with(['0', '4', 'DD']);

it('accepts every issue reason allowed for debit notes', function (string $reason): void {
    expect(DebitNoteData::from(P::correction(['issueReasonCode' => $reason]))->issueReason)->toBe(IssueReason::from($reason));
})->with(['2', '3', '4', '6', '8', '9', 'IN', 'DD']);

it('rejects every issue reason not allowed for debit notes', function (string $reason): void {
    $payload = P::correction(['issueReasonCode' => $reason]);

    expect(fn (): DebitNoteData => DebitNoteData::from($payload))
        ->toFailValidationOn('issueReasonCode', 'The selected issue reason code is invalid.');
})->with(['0', '7', 'DRP']);

it('accepts every issue reason allowed for return notes', function (string $reason): void {
    expect(ReturnNoteData::from(P::returnNote($reason))->issueReason)->toBe(IssueReason::from($reason));
})->with(['0', '2', '3', '6', '7', '8', '9', 'IN']);

it('rejects every issue reason not allowed for return notes', function (string $reason): void {
    $payload = P::returnNote($reason);

    expect(fn (): ReturnNoteData => ReturnNoteData::from($payload))
        ->toFailValidationOn('issueReasonCode', 'The selected issue reason code is invalid.');
})->with(['4', 'DD', 'DRP']);
