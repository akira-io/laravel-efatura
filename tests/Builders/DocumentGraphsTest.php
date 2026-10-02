<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DatePeriodData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\InvoiceData;
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
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});
afterEach(fn () => CarbonImmutable::setTestNow());

it('builds every canonical document graph without adding forbidden sections', function (DocumentType $type, string $class): void {
    $draft = Efatura::invoice()->type($type)->emitter(B::emitter(), 1)
        ->receiver(PartyData::from(F::payload()['receiver']))
        ->reference(ReferenceData::from(F::references()[0]));
    if ($type !== DocumentType::Receipt) {
        $draft->line(F::line());
        if ($type !== DocumentType::Transport) {
            $draft->totals(F::totals());
        }
    }

    if (in_array($type, [DocumentType::InvoiceReceipt, DocumentType::SalesReceipt, DocumentType::Receipt, DocumentType::RegistrationNote], true)) {
        $draft->payments(PaymentsData::from(F::payments()));
    }

    if ($type === DocumentType::SalesReceipt) {
        $draft = Efatura::invoice()->type($type)->emitter(B::emitter(), 1)->line(F::line())->totals(F::totals())->payments(PaymentsData::from(F::payments()));
    }

    if (in_array($type, [DocumentType::CreditNote, DocumentType::DebitNote, DocumentType::ReturnNote], true)) {
        $draft->issueReason(IssueReason::Article65Paragraph2);
    }

    if ($type === DocumentType::ReturnNote) {
        $draft->issueReasonDescription('Goods returned by buyer');
    }

    if ($type === DocumentType::Receipt) {
        $draft->receiptType(ReceiptType::Commercial);
    }

    if ($type === DocumentType::Transport) {
        $draft->transportDocumentType(TransportDocumentType::Dispatch)->transportServiceProvider(PartyData::from(['reference' => 'EP']))
            ->transportRoute(TransportRouteData::from(F::route()))->receiverType(TransportReceiverType::Taxpayer);
    }

    $document = $draft->build();
    expect($document)->toBeInstanceOf($class)->and($document->type())->toBe($type);
})->with([
    [DocumentType::Invoice, ElectronicInvoiceData::class],
    [DocumentType::InvoiceReceipt, ReceiptInvoiceData::class],
    [DocumentType::SalesReceipt, SalesReceiptData::class],
    [DocumentType::Receipt, ReceiptData::class],
    [DocumentType::CreditNote, CreditNoteData::class],
    [DocumentType::DebitNote, DebitNoteData::class],
    [DocumentType::ReturnNote, ReturnNoteData::class],
    [DocumentType::RegistrationNote, RegistrationNoteData::class],
    [DocumentType::Transport, TransportDocumentData::class],
]);

it('preserves explicit header context footer and invoice optional fields', function (): void {
    $draft = Efatura::invoice()->emitter(B::emitter(), 1)->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())
        ->header(DocumentHeaderData::from([...F::payload()['header'], 'serie' => 'A', 'documentNumber' => 8, 'innerDocumentNumber' => 'HOST-8', 'isIsolatedAct' => true,
            'selfBilling'                                                     => ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234']]))
        ->issuedAt(new CarbonImmutable('2026-10-02T10:00:00-01:00'))
        ->dueDate(new CarbonImmutable('2026-10-31'))->taxPointDate(new CarbonImmutable('2026-10-01'))->orderReference('ORDER-1')
        ->delivery(DeliveryData::from(['deliveryDate' => '2026-10-02', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]))
        ->emission(EmissionContextData::from(['transmitterTaxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'software' => ['code' => 'APP', 'name' => 'Fiscal App', 'version' => '1.0']]))
        ->footer(DocumentFooterData::from(['note' => 'Customer delivery note', 'extraFields' => [['name' => 'CustomerHint', 'value' => 'Ready']]]));
    $document = $draft->build();
    expect($document->header->issueTime->format('H:i:s'))->toBe('10:00:00')->and($document->header->documentNumber)->toBe(8)
        ->and($document->header->selfBilling->authorizationCode)->toBe('1234')->and($document->dueDate->format('Y-m-d'))->toBe('2026-10-31')
        ->and($document->taxPointDate->format('Y-m-d'))->toBe('2026-10-01')->and($document->orderReference)->toBe('ORDER-1')
        ->and($document->delivery->address->addressDetail)->toBe('Lisbon')->and($document->emission->software->code)->toBe('APP')
        ->and($document->footer->extraFields[0]->value)->toBe('Ready');
    $draft->type(DocumentType::Receipt);
    expect(fn (): InvoiceData => $draft->build())->toThrow(ValidationException::class);
});

it('supports rent payment parties and credit note periods', function (): void {
    $receipt = Efatura::invoice()->type(DocumentType::Receipt)->emitter(B::emitter(), 1)->receiver(PartyData::from(F::payload()['receiver']))
        ->receiptType(ReceiptType::Rent)->reference(ReferenceData::from(F::references()[0]))->payments(PaymentsData::from(F::payments()))
        ->paymentParty(PartyData::from(['reference' => 'RP']))
        ->rentReceipt(RentReceiptData::from(['assetId' => 'ASSET-1', 'rentPurposeTypeCode' => '1', 'contractTypeCode' => '1', 'rentTypeCode' => '1', 'referencePeriod' => '2026-10', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]))->build();
    expect($receipt->paymentParty->reference->value)->toBe('RP')->and($receipt->rentReceipt->assetId)->toBe('ASSET-1');
    $credit = Efatura::invoice()->type(DocumentType::CreditNote)->emitter(B::emitter(), 1)->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())
        ->issueReason(IssueReason::RappelDiscount)->reference(ReferenceData::from(F::references()[0]))
        ->rappelPeriod(DatePeriodData::from(['startDate' => '2026-09-01', 'endDate' => '2026-09-30']))->build();
    expect($credit->rappelPeriod->endDate->format('Y-m-d'))->toBe('2026-09-30');
});
