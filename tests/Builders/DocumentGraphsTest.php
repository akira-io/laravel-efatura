<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentGraphs as G;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

dataset('document graphs', [
    'invoice'         => [DocumentType::Invoice, ElectronicInvoiceData::class, G::invoiced(...), ['header', 'emitter', 'receiver', 'lines', 'totals']],
    'invoice receipt' => [DocumentType::InvoiceReceipt, ReceiptInvoiceData::class, G::paidInvoice(...), ['header', 'emitter', 'receiver', 'lines', 'totals', 'payments']],
    'sales receipt'   => [DocumentType::SalesReceipt, SalesReceiptData::class, G::salesReceipt(...), ['header', 'emitter', 'lines', 'totals', 'payments']],
    'receipt'         => [DocumentType::Receipt, ReceiptData::class, G::receipt(...), ['header', 'emitter', 'receiver', 'references', 'payments', 'receiptTypeCode']],
    'credit note'     => [DocumentType::CreditNote, CreditNoteData::class, G::correction(...), ['header', 'emitter', 'receiver', 'lines', 'totals', 'references', 'issueReasonCode']],
    'debit note'      => [DocumentType::DebitNote, DebitNoteData::class, G::correction(...), ['header', 'emitter', 'receiver', 'lines', 'totals', 'references', 'issueReasonCode']],
    'return note'     => [DocumentType::ReturnNote, ReturnNoteData::class, G::returnNote(...), [
        'header', 'emitter', 'receiver', 'lines', 'totals', 'references', 'issueReasonCode', 'issueReasonDescription',
    ]],
    'registration note' => [DocumentType::RegistrationNote, RegistrationNoteData::class, G::registrationNote(...), ['header', 'emitter', 'receiver', 'lines', 'totals', 'references', 'payments']],
    'transport'         => [DocumentType::Transport, TransportDocumentData::class, G::transport(...), [
        'header', 'emitter', 'receiver', 'lines', 'references', 'transportDocumentTypeCode', 'transportServiceProvider', 'transportRoute', 'receiverTypeCode',
    ]],
]);

it('builds every canonical document graph with only the sections it was given', function (DocumentType $type, string $class, Closure $configure, array $sections): void {
    $document = $configure(Efatura::invoice()->type($type)->emitter(B::emitter(), 1))->build();

    expect($document)->toBeInstanceOf($class)
        ->and($document->type())->toBe($type)
        ->and(array_keys(array_filter($document->toArray(), fn (mixed $value): bool => $value !== null && $value !== [])))
        ->toEqualCanonicalizing($sections);
})->with('document graphs');

it('preserves explicit header context footer and invoice optional fields', function (): void {
    $header = DocumentHeaderData::from([...F::payload()['header'], 'serie' => 'A', 'documentNumber' => 8, 'innerDocumentNumber' => 'HOST-8', 'isIsolatedAct' => true,
        'selfBilling'                                                      => ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234']]);
    $emission = EmissionContextData::from(['transmitterTaxId' => ['value' => '123456789', 'countryCode' => 'CV'],
        'software'                                            => ['code' => 'APP', 'name' => 'Fiscal App', 'version' => '1.0']]);
    $document = Efatura::invoice()->emitter(B::emitter(), 1)->receiver(B::receiver())->line(F::line())->totals(F::totals())
        ->header($header)->issuedAt(new CarbonImmutable('2026-10-02T10:00:00-01:00'))
        ->dueDate(new CarbonImmutable('2026-10-31'))->taxPointDate(new CarbonImmutable('2026-10-01'))->orderReference('ORDER-1')
        ->delivery(DeliveryData::from(['deliveryDate' => '2026-10-02', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]))
        ->emission($emission)
        ->footer(DocumentFooterData::from(['note' => 'Customer delivery note', 'extraFields' => [['name' => 'CustomerHint', 'value' => 'Ready']]]))
        ->build();

    expect($document->header->issueTime->format('H:i:s'))->toBe('10:00:00')
        ->and($document->header->documentNumber)->toBe(8)
        ->and($document->header->selfBilling->authorizationCode)->toBe('1234')
        ->and($document->dueDate->format('Y-m-d'))->toBe('2026-10-31')
        ->and($document->taxPointDate->format('Y-m-d'))->toBe('2026-10-01')
        ->and($document->orderReference)->toBe('ORDER-1')
        ->and($document->delivery->address->addressDetail)->toBe('Lisbon')
        ->and($document->emission->software->code)->toBe('APP')
        ->and($document->footer->extraFields[0]->value)->toBe('Ready');
});

it('rejects invoice sections on a receipt', function (): void {
    $draft = Efatura::invoice()->type(DocumentType::Receipt)->emitter(B::emitter(), 1)->receiver(B::receiver())->line(F::line())->totals(F::totals());

    expect(fn (): DocumentData => $draft->build())->toFailValidationOn('totals', 'This field does not belong to this fiscal document type.');
});

it('supports rent receipts with a payment party', function (): void {
    $rent = RentReceiptData::from(['assetId' => 'ASSET-1', 'rentPurposeTypeCode' => '1', 'contractTypeCode' => '1', 'rentTypeCode' => '1',
        'referencePeriod'                    => '2026-10', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]);
    $receipt = Efatura::invoice()->type(DocumentType::Receipt)->emitter(B::emitter(), 1)->receiver(B::receiver())
        ->receiptType(ReceiptType::Rent)->reference(ReferenceData::from(F::references()[0]))->payments(PaymentsData::from(F::payments()))
        ->paymentParty(PartyData::from(['reference' => 'RP']))->rentReceipt($rent)->build();

    expect($receipt->paymentParty->reference->value)->toBe('RP')
        ->and($receipt->rentReceipt->assetId)->toBe('ASSET-1');
});

it('supports credit note rappel periods', function (): void {
    $credit = Efatura::invoice()->type(DocumentType::CreditNote)->emitter(B::emitter(), 1)->receiver(B::receiver())->line(F::line())->totals(F::totals())
        ->issueReason(IssueReason::RappelDiscount)->reference(ReferenceData::from(F::references()[0]))
        ->rappelPeriod(DatePeriodData::from(['startDate' => '2026-09-01', 'endDate' => '2026-09-30']))->build();

    expect($credit->rappelPeriod->startDate->format('Y-m-d'))->toBe('2026-09-01')
        ->and($credit->rappelPeriod->endDate->format('Y-m-d'))->toBe('2026-09-30');
});
