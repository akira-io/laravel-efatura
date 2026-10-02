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
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00'));
it('constructs all nine concrete fiscal document graphs', function (string $class, string $type, array $changes, array $remove): void {
    $payload = F::payload($changes);
    foreach ($remove as $key) {
        unset($payload[$key]);
    }

    foreach (['from', 'validateAndCreate'] as $factory) {
        $document = $class::$factory($payload);
        expect($document)->toBeInstanceOf(DocumentData::class)->and($document->type())->toBe(DocumentType::from($type))->and($document->header->documentNumber)->toBeNull();
    }
})->with([
    'invoice'         => [ElectronicInvoiceData::class, 'FTE', [], []],
    'invoice receipt' => [ReceiptInvoiceData::class, 'FRE', ['payments' => F::payments()], []],
    'sales receipt'   => [SalesReceiptData::class, 'TVE', ['payments' => F::payments(), 'receiver' => null], []],
    'receipt'         => [ReceiptData::class, 'RCE', ['receiptTypeCode' => '1', 'references' => F::references(), 'payments' => F::payments()], ['lines', 'totals']],
    'credit note'     => [CreditNoteData::class, 'NCE', ['issueReasonCode' => 'DRP', 'references' => F::references()], []],
    'debit note'      => [DebitNoteData::class, 'NDE', ['issueReasonCode' => 'DD', 'references' => F::references()], []],
    'return note'     => [ReturnNoteData::class, 'DVE', ['issueReasonCode' => '0', 'issueReasonDescription' => 'Goods returned by buyer', 'references' => F::references()], []],
    'registration'    => [RegistrationNoteData::class, 'NLE', [], []],
    'transport'       => [TransportDocumentData::class, 'DTE', ['transportDocumentTypeCode' => '2', 'transportServiceProvider' => ['reference' => 'EP'], 'transportRoute' => F::route(), 'receiverTypeCode' => '3', 'receiver' => null, 'lines' => [F::linePayload(['price' => null, 'priceExtension' => null, 'netTotal' => null, 'taxes' => []])]], ['totals']],
]);
it('rejects invalid document combinations through both canonical factories', function (string $class, array $changes): void {
    foreach (['from', 'validateAndCreate'] as $factory) {
        expect(fn () => $class::$factory(F::payload($changes)))->toThrow(ValidationException::class);
    }
})->with([
    'missing lines'            => [ElectronicInvoiceData::class, ['lines' => []]],
    'receiver required'        => [ElectronicInvoiceData::class, ['receiver' => null]],
    'credit references'        => [CreditNoteData::class, ['issueReasonCode' => '2']],
    'debit references'         => [DebitNoteData::class, ['issueReasonCode' => 'DD']],
    'return references'        => [ReturnNoteData::class, ['issueReasonCode' => 'IN']],
    'invoice payments'         => [ElectronicInvoiceData::class, ['payments' => F::payments()]],
    'receipt invoice payments' => [ReceiptInvoiceData::class, []],
    'sales payments'           => [SalesReceiptData::class, []],
    'incompatible reason'      => [CreditNoteData::class, ['issueReasonCode' => 'DD', 'references' => F::references()]],
    'other reason description' => [ReturnNoteData::class, ['issueReasonCode' => '0', 'references' => F::references()]],
    'forbidden reason'         => [ElectronicInvoiceData::class, ['issueReasonCode' => '2']],
    'forbidden rappel'         => [DebitNoteData::class, ['issueReasonCode' => 'DD', 'references' => F::references(), 'rappelPeriod' => ['startDate' => '2026-01-01', 'endDate' => '2026-02-01']]],
    'missing tax'              => [ElectronicInvoiceData::class, ['lines' => [F::linePayload(['taxes' => []])]]],
    'missing price evidence'   => [ElectronicInvoiceData::class, ['lines' => [F::linePayload(['price' => null])]]],
    'duplicate line ids'       => [ElectronicInvoiceData::class, ['lines' => [F::linePayload(['id' => 'A']), F::linePayload(['id' => 'A'])]]],
    'missing charge target'    => [ElectronicInvoiceData::class, ['lines' => [F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => 'missing'])]]],
    'receiver self reference'  => [ElectronicInvoiceData::class, ['receiver' => ['reference' => 'RP']]],
]);
it('applies TVE receiver threshold to exact payable amounts', function (string $amount, bool $valid): void {
    $payload = F::payload(['receiver' => null, 'payments' => F::payments(),
        'lines'                       => [F::linePayload(['price' => $amount, 'priceExtension' => $amount, 'netTotal' => $amount, 'taxes' => [['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1']]])],
        'totals'                      => F::totalsPayload(['priceExtensionTotalAmount' => $amount, 'netTotalAmount' => $amount, 'taxTotalAmount' => '0', 'payableAmount' => $amount])]);
    if ($valid) {
        expect(SalesReceiptData::from($payload)->receiver)->toBeNull();
    } else {
        expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))->toThrow(ValidationException::class);
    }
})->with([['19999.99999', true], ['20000', false], ['20000.00001', false]]);
