<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

dataset('canonical factories', ['from', 'validateAndCreate']);

it('accepts a return note without a receiver', function (string $factory): void {
    $payload = P::correction();
    unset($payload['receiver']);

    expect(ReturnNoteData::$factory($payload)->receiver)->toBeNull();
})->with('canonical factories');

it('keeps a supplied return receiver', function (string $factory, array $payload): void {
    expect(ReturnNoteData::$factory($payload)->receiver?->taxId?->countryCode)->toBe('CV');
})->with('canonical factories')->with([
    'without self billing' => [P::correction()],
    'with self billing'    => [P::selfBilled(P::correction())],
]);

it('requires a receiver on a self billed return note', function (string $factory): void {
    $payload = P::selfBilled(P::correction());
    unset($payload['receiver']);

    expect(fn (): ReturnNoteData => ReturnNoteData::$factory($payload))
        ->toFailValidationOn('receiver', 'The receiver field is required.');
})->with('canonical factories');

it('accepts transport receivers with a Cabo Verde identity or an emitter reference', function (string $factory, string $type, array $receiver): void {
    $payload = P::transport(['receiver' => $receiver, 'receiverTypeCode' => $type]);

    expect(TransportDocumentData::$factory($payload)->receiver->toArray())->toBe(PartyData::from($receiver)->toArray());
})->with('canonical factories')->with([
    'local taxpayer'       => ['1', ['taxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'name' => 'Local buyer']],
    'emitter reference'    => ['1', ['reference' => 'EP']],
    'foreign non taxpayer' => ['2', P::foreignBuyer()],
]);

it('rejects a foreign identity for a transport taxpayer receiver', function (string $factory): void {
    $payload = P::transport(['receiverTypeCode' => '1', 'receiver' => P::foreignBuyer()]);

    expect(fn (): TransportDocumentData => TransportDocumentData::$factory($payload))
        ->toFailValidationOn('receiver.taxId.countryCode', 'The selected receiver.tax id.country code is invalid.');
})->with('canonical factories');

dataset('documents requiring a receiver', [
    'invoice'         => [ElectronicInvoiceData::class, F::payload()],
    'invoice receipt' => [ReceiptInvoiceData::class, F::payload(['payments' => F::payments()])],
    'receipt'         => [ReceiptData::class, F::receiptPayload('1')],
    'credit note'     => [CreditNoteData::class, P::correction(['issueReasonCode' => 'DRP'])],
    'debit note'      => [DebitNoteData::class, P::correction(['issueReasonCode' => 'DD'])],
    'registration'    => [RegistrationNoteData::class, F::payload()],
]);

it('reports a missing receiver once at the receiver field', function (string $class, array $payload, Closure $missing): void {
    $payload = $missing($payload);

    expect(fn (): DocumentData => $class::from($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe(['receiver' => ['The receiver field is required.']]);
    });
})->with('documents requiring a receiver')->with([
    'null'   => fn (array $payload): array => [...$payload, 'receiver' => null],
    'empty'  => fn (array $payload): array => [...$payload, 'receiver' => []],
    'absent' => fn (array $payload): array => array_diff_key($payload, ['receiver' => true]),
]);

it('reports only the nested fields of a receiver given without an identity', function (string $class, array $payload): void {
    $payload = [...$payload, 'receiver' => ['address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]];

    expect(fn (): DocumentData => $class::from($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe([
            'receiver.taxId' => ['The receiver.tax id field is required when receiver.reference is not present.'],
            'receiver.name'  => ['The receiver.name field is required when receiver.reference is not present.'],
        ]);
    });
})->with('documents requiring a receiver');
