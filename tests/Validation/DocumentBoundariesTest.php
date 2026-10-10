<?php

declare(strict_types=1);
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('rejects explicit empty constrained document text', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): DocumentData => $class::from($payload))->toFailValidationOn($field, $message);
})->with([
    'order reference'          => [ElectronicInvoiceData::class, F::payload(['orderReference' => '']), 'orderReference', 'The order reference field must have a value.'],
    'issue reason description' => [ReturnNoteData::class, P::correction(['issueReasonDescription' => ' ']), 'issueReasonDescription', 'The issue reason description field must have a value.'],
]);

it('keeps fiscal header values explicit', function (): void {
    $header = DocumentHeaderData::from(P::allocatedHeader());

    expect($header->documentNumber)->toBe(999999999)
        ->and($header->series)->toBe('A-1')
        ->and($header->ledCode)->toBe(99999)
        ->and($header->innerDocumentNumber)->toBe('INV-1');
});

it('validates supplied header allocations', function (string $field, int|string $value, string $message): void {
    $payload = P::allocatedHeader([$field => $value]);

    expect(fn (): DocumentHeaderData => DocumentHeaderData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'led code'              => ['ledCode', 0, 'The led code field must be between 1 and 99999.'],
    'series'                => ['serie', 'bad space', 'The serie field format is invalid.'],
    'document number'       => ['documentNumber', 0, 'The document number field must be between 1 and 999999999.'],
    'inner document number' => ['innerDocumentNumber', '', 'The inner document number field must have a value.'],
]);

it('preserves empty footer extension content', function (): void {
    $footer = DocumentFooterData::validateAndCreate(['note' => 'Customer delivery note', 'extraFields' => [['name' => 'CustomerHint', 'value' => '']]]);

    expect($footer->extraFields[0]->value)->toBe('')
        ->and($footer->extraFields[0]->name)->toBe('CustomerHint');
});

it('rejects a footer note below its minimum length', function (): void {
    $payload = ['note' => 'short'];

    expect(fn (): DocumentFooterData => DocumentFooterData::from($payload))
        ->toFailValidationOn('note', 'The note field must be at least 10 characters.');
});

it('accepts an issuance exactly at the seven day contingency floor', function (): void {
    CarbonImmutable::setTestNow('2026-10-09T12:00:00-01:00');
    $emission = EmissionContextData::from(['issueMode' => 2, 'contingency' => P::offlineContingency()]);

    expect(B::issuance(P::header())->emission($emission)->build()->header->issueDate->format('Y-m-d'))->toBe('2026-10-02');
});

it('rejects an issuance one second before the seven day contingency floor', function (): void {
    CarbonImmutable::setTestNow('2026-10-09T12:00:00-01:00');
    $emission = EmissionContextData::from(['issueMode' => 2, 'contingency' => P::offlineContingency()]);
    $draft    = B::issuance(P::header(['issueTime' => '11:59:59']))->emission($emission);

    expect(fn (): DocumentData => $draft->build())
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['header.issueDate' => ['The issue date and time are outside the permitted emission window.']]);
        });
});

it('sets no future bound on contingency issuance, as Manual IDT-IMC-GE does not', function (array $emission): void {
    $document = B::issuance(P::header(['issueTime' => '14:00:00']))->emission(EmissionContextData::from($emission))->build();

    expect($document->header->issueTime->format('H:i:s'))->toBe('14:00:00');
})->with([
    'offline' => [['issueMode' => 2, 'contingency' => P::offlineContingency()]],
    'off'     => [['issueMode' => 3, 'contingency' => P::contingency(ContingencyReason::PowerFailure)]],
]);

it('rejects a tax point after the issue date', function (): void {
    $payload = F::payload(['taxPointDate' => '2026-10-03']);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('taxPointDate', 'The tax point date cannot be later than the issue date.');
});

it('rejects an immediate payment dated before the issue date', function (): void {
    $payload = F::payload(['payments' => ['payments' => [['paymentDate' => '2026-10-01']]]]);

    expect(fn (): ReceiptInvoiceData => ReceiptInvoiceData::from($payload))
        ->toFailValidationOn('payments.payments.0.paymentDate', 'The payment date must be the issue date.');
});

it('accepts a past tax point with a due date and order reference', function (): void {
    $invoice = ElectronicInvoiceData::from(F::payload(['taxPointDate' => '2026-10-01', 'dueDate' => '2026-10-31', 'orderReference' => 'ORDER-1']));

    expect($invoice->dueDate->format('Y-m-d'))->toBe('2026-10-31')
        ->and($invoice->taxPointDate->format('Y-m-d'))->toBe('2026-10-01');
});

it('keeps supplied transmission identities without inventing software', function (): void {
    $context = EmissionContextData::validateAndCreate(P::transmission());

    expect($context->software->code)->toBe('APP')
        ->and($context->transmitterTaxId->value)->toBe('123456789');
});

it('rejects a malformed transmitter identity', function (): void {
    $payload = ['transmitterTaxId' => ['value' => '123', 'countryCode' => 'PT']];

    expect(fn (): EmissionContextData => EmissionContextData::from($payload))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe([
                'transmitterTaxId.value'       => ['The transmitter tax id.value must be a valid tax identifier for its country.'],
                'transmitterTaxId.countryCode' => ['The selected transmitter tax id.country code is invalid.'],
            ]);
        });
});

it('requires a real buyer when self billing a sales receipt', function (): void {
    $payload = P::selfBilled(F::payload(['receiver' => null, 'payments' => F::payments()]));

    expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))
        ->toFailValidationOn('receiver', 'The receiver field is required.');
});

it('resolves a transport provider reference to the supplied receiver', function (): void {
    expect(TransportDocumentData::from(P::transport(['transportServiceProvider' => ['reference' => 'RP']]))->transportServiceProvider->reference->value)->toBe('RP');
});

it('requires the receiver a transport provider reference points to', function (): void {
    $payload = P::transport(['transportServiceProvider' => ['reference' => 'RP'], 'receiver' => null, 'receiverTypeCode' => '3']);

    expect(fn (): TransportDocumentData => TransportDocumentData::from($payload))
        ->toFailValidationOn('receiver', 'The receiver field is required.');
});

it('requires charge references to target normal lines', function (): void {
    $payload = P::chargeOnInformationLine();

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('lines.1.lineReferenceId', 'The selected lines.1.lineReferenceId is invalid.');
});

it('recognizes zero as a valid line id', function (): void {
    expect(ElectronicInvoiceData::from(P::chargedInvoice('0', '0'))->lines[1]->lineReferenceId)->toBe('0');
});

it('detects duplicates of the zero line id', function (): void {
    $payload = P::duplicateLineIds('0');

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('lines.1.id', 'The lines.1.id field has a duplicate value.');
});

it('limits the issue date to the years an identifier can carry', function (): void {
    $header = fn (string $issueDate): array => F::payload(['header' => ['issueDate' => $issueDate, 'issueTime' => '12:00:00', 'ledCode' => 1]]);

    expect(ElectronicInvoiceData::from($header('2099-12-31'))->header->issueDate->year)->toBe(2099)
        ->and(fn (): DocumentData => ElectronicInvoiceData::from($header('2100-01-01')))
        ->toFailValidationOn('header.issueDate', 'The header.issue date field must be a date before 2100-01-01.');
});
