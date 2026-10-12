<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentGraphs as G;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

dataset('document types by section', [
    'FTE' => [ElectronicInvoiceData::class, F::payload(), true, 'optional'],
    'FRE' => [ReceiptInvoiceData::class, F::payload(['payments' => F::payments()]), true, 'optional'],
    'TVE' => [SalesReceiptData::class, F::payload(['payments' => F::payments()]), false, 'prohibited'],
    'RCE' => [ReceiptData::class, F::receiptPayload('1'), true, 'required'],
    'NCE' => [CreditNoteData::class, P::correction(['issueReasonCode' => 'DRP']), true, 'required'],
    'NDE' => [DebitNoteData::class, P::correction(['issueReasonCode' => 'DD']), true, 'required'],
    'DTE' => [TransportDocumentData::class, P::transport(), false, 'prohibited'],
    'DVE' => [ReturnNoteData::class, P::returnNote('0'), true, 'required'],
    'NLE' => [RegistrationNoteData::class, F::payload(), false, 'prohibited'],
]);

dataset('section combinations', [
    'plain'                       => [false, false],
    'self billed'                 => [true, false],
    'referenced'                  => [false, true],
    'self billed with references' => [true, true],
]);

it('accepts self billing and references only on the document types the manual allows', function (
    string $class,
    array $payload,
    bool $selfBillable,
    string $references,
    bool $selfBilled,
    bool $referenced,
): void {
    $payload = array_diff_key($payload, ['references' => true]);
    $payload = $selfBilled ? P::selfBilled($payload) : $payload;
    $payload = $referenced ? [...$payload, 'references' => F::references()] : $payload;

    $refused = array_filter([
        'header.selfBilling' => $selfBilled && ! $selfBillable ? ['This field does not belong to this fiscal document type.'] : null,
        'references'         => $referenced && $references === 'prohibited' ? ['This field does not belong to this fiscal document type.'] : null,
    ]);
    $refused = ! $referenced && $references === 'required' ? [...$refused, 'references' => ['The references field must be present.']] : $refused;

    if ($refused === []) {
        expect($class::from($payload))->toBeInstanceOf($class)
            ->and($class::from($payload)->header->selfBilling !== null)->toBe($selfBilled);

        return;
    }

    expect(fn (): DocumentData => $class::from($payload))->toThrow(function (ValidationException $exception) use ($refused): void {
        expect($exception->errors())->toEqual($refused);
    });
})->with('document types by section')->with('section combinations');

it('accepts an explicitly empty reference list on documents that cannot carry references', function (string $class, array $payload): void {
    expect($class::from([...$payload, 'references' => []])->references)->toBe([]);
})->with([
    'DTE' => [TransportDocumentData::class, P::transport()],
    'NLE' => [RegistrationNoteData::class, F::payload()],
]);

it('refuses a self billed header on a built transport document', function (): void {
    $header = DocumentHeaderData::from(P::header(['selfBilling' => P::selfBilling()]));
    $draft  = G::transport(Efatura::invoice()->type(DocumentType::Transport)->emitter(B::emitter(), 1)->header($header));

    expect(fn (): DocumentData => $draft->build())->toFailValidationOn('header.selfBilling', 'This field does not belong to this fiscal document type.');
});

it('refuses a reference on a built registration note', function (): void {
    $draft = G::registrationNote(Efatura::invoice()->type(DocumentType::RegistrationNote)->emitter(B::emitter(), 1)
        ->header(DocumentHeaderData::from(P::header()))->reference(ReferenceData::from(F::references()[0])));

    expect(fn (): DocumentData => $draft->build())->toFailValidationOn('references', 'This field does not belong to this fiscal document type.');
});
