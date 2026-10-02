<?php

declare(strict_types=1);
use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

dataset('canonical factories', ['from', 'validateAndCreate']);

it('constructs all nine concrete fiscal document graphs', function (string $class, string $type, array $payload, string $factory): void {
    $document = $class::$factory($payload);

    expect($document->type())->toBe(DocumentType::from($type))
        ->and($document->header->documentNumber)->toBeNull();
})->with([
    'invoice'         => [ElectronicInvoiceData::class, 'FTE', F::payload()],
    'invoice receipt' => [ReceiptInvoiceData::class, 'FRE', F::payload(['payments' => F::payments()])],
    'sales receipt'   => [SalesReceiptData::class, 'TVE', F::payload(['payments' => F::payments(), 'receiver' => null])],
    'receipt'         => [ReceiptData::class, 'RCE', F::receiptPayload('1')],
    'credit note'     => [CreditNoteData::class, 'NCE', P::correction(['issueReasonCode' => 'DRP'])],
    'debit note'      => [DebitNoteData::class, 'NDE', P::correction(['issueReasonCode' => 'DD'])],
    'return note'     => [ReturnNoteData::class, 'DVE', P::returnNote('0')],
    'registration'    => [RegistrationNoteData::class, 'NLE', F::payload()],
    'transport'       => [TransportDocumentData::class, 'DTE', P::unpricedTransport()],
])->with('canonical factories');

it('rejects invalid document combinations through both canonical factories', function (string $class, array $payload, string $field, string $message, string $factory): void {
    expect(fn (): DocumentData => $class::$factory($payload))->toFailValidationOn($field, $message);
})->with([
    'missing lines'            => [ElectronicInvoiceData::class, F::payload(['lines' => []]), 'lines', 'The lines field must have at least 1 items.'],
    'receiver required'        => [ElectronicInvoiceData::class, F::payload(['receiver' => null]), 'receiver', 'The receiver field is required.'],
    'credit references'        => [CreditNoteData::class, F::payload(['issueReasonCode' => '2']), 'references', 'The references field must be present.'],
    'debit references'         => [DebitNoteData::class, F::payload(['issueReasonCode' => 'DD']), 'references', 'The references field must be present.'],
    'return references'        => [ReturnNoteData::class, F::payload(['issueReasonCode' => 'IN']), 'references', 'The references field must be present.'],
    'invoice payments'         => [ElectronicInvoiceData::class, F::payload(['payments' => F::payments()]), 'payments.payments', 'The payments.payments field is prohibited.'],
    'receipt invoice payments' => [ReceiptInvoiceData::class, F::payload(), 'payments', 'The payments field is required.'],
    'sales payments'           => [SalesReceiptData::class, F::payload(), 'payments', 'The payments field is required.'],
    'incompatible reason'      => [CreditNoteData::class, P::correction(['issueReasonCode' => 'DD']), 'issueReasonCode', 'The selected issue reason code is invalid.'],
    'other reason description' => [ReturnNoteData::class, P::correction(['issueReasonCode' => '0']), 'issueReasonDescription', 'The issue reason description field is required.'],
    'forbidden reason'         => [ElectronicInvoiceData::class, F::payload(['issueReasonCode' => '2']), 'issueReasonCode', 'This field does not belong to this fiscal document type.'],
    'forbidden rappel'         => [DebitNoteData::class, P::correction(['issueReasonCode' => 'DD', 'rappelPeriod' => ['startDate' => '2026-01-01', 'endDate' => '2026-02-01']]),
        'rappelPeriod', 'This field does not belong to this fiscal document type.'],
    'missing tax'            => [ElectronicInvoiceData::class, F::payload(['lines' => [F::linePayload(['taxes' => []])]]), 'lines.0.taxes', 'The lines.0.taxes field is required.'],
    'missing price evidence' => [ElectronicInvoiceData::class, F::payload(['lines' => [F::linePayload(['price' => null])]]), 'lines.0.price', 'The lines.0.price field is required.'],
    'duplicate line ids'     => [ElectronicInvoiceData::class, P::duplicateLineIds('A'), 'lines.1.id', 'The lines.1.id field has a duplicate value.'],
    'missing charge target'  => [ElectronicInvoiceData::class, F::payload(['lines' => [F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => 'missing'])]]),
        'lines.0.lineReferenceId', 'The selected lines.0.lineReferenceId is invalid.'],
    'receiver self reference' => [ElectronicInvoiceData::class, F::payload(['receiver' => ['reference' => 'RP']]), 'receiver.reference', 'The selected receiver.reference is invalid.'],
])->with('canonical factories');

it('accepts an anonymous sales receipt below the receiver threshold', function (): void {
    expect(SalesReceiptData::from(P::exemptAnonymousSalesReceipt('19999.99999'))->receiver)->toBeNull();
});

it('requires a receiver on sales receipts from the exact payable threshold', function (string $amount): void {
    $payload = P::exemptAnonymousSalesReceipt($amount);

    expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))
        ->toFailValidationOn('receiver', 'The receiver field is required.');
})->with(['20000', '20000.00001']);
