<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('allows each correction document only its own issue reasons', function (DocumentType $type, array $reasons): void {
    expect(IssueReason::allowedFor($type))->toBe($reasons);
})->with([
    'credit note' => [DocumentType::CreditNote, [
        IssueReason::Article65Paragraph2, IssueReason::Article65Paragraph3, IssueReason::Article65Paragraph6, IssueReason::Article65Paragraph8,
        IssueReason::Article65Paragraph9, IssueReason::Unavailable, IssueReason::Article65Paragraph7, IssueReason::RappelDiscount,
    ]],
    'debit note' => [DocumentType::DebitNote, [
        IssueReason::Article65Paragraph2, IssueReason::Article65Paragraph3, IssueReason::Article65Paragraph6, IssueReason::Article65Paragraph8,
        IssueReason::Article65Paragraph9, IssueReason::Unavailable, IssueReason::Article65Paragraph4, IssueReason::ExpenseDebit,
    ]],
    'return note' => [DocumentType::ReturnNote, [
        IssueReason::Article65Paragraph2, IssueReason::Article65Paragraph3, IssueReason::Article65Paragraph6, IssueReason::Article65Paragraph8,
        IssueReason::Article65Paragraph9, IssueReason::Unavailable, IssueReason::Article65Paragraph7, IssueReason::Other,
    ]],
    'invoice'           => [DocumentType::Invoice, []],
    'invoice receipt'   => [DocumentType::InvoiceReceipt, []],
    'sales receipt'     => [DocumentType::SalesReceipt, []],
    'receipt'           => [DocumentType::Receipt, []],
    'transport'         => [DocumentType::Transport, []],
    'registration note' => [DocumentType::RegistrationNote, []],
]);

it('derives the line policy from the document type', function (): void {
    expect(DocumentType::Transport->requiresLinePricing())->toBeFalse()
        ->and(DocumentType::CreditNote->requiresLinePricing())->toBeTrue()
        ->and(DocumentType::CreditNote->requiresLineTaxes())->toBeFalse()
        ->and(DocumentType::ReturnNote->requiresLineTaxes())->toBeFalse()
        ->and(DocumentType::Transport->requiresLineTaxes())->toBeFalse()
        ->and(DocumentType::Invoice->requiresLineTaxes())->toBeTrue();
});

it('reports document compatibility failures at their full path', function (string $class, array $payload, array $errors): void {
    expect(fn (): DocumentData => $class::from($payload))->toThrow(function (ValidationException $exception) use ($errors): void {
        expect($exception->errors())->toBe($errors);
    });
})->with([
    'issue reason of another type' => [CreditNoteData::class, P::correction(['issueReasonCode' => '4']), ['issueReasonCode' => ['The selected issue reason code is invalid.']]],
    'missing references'           => [CreditNoteData::class, P::correction(['references' => []]), ['references' => ['The references field is required.']]],
    'other reason without text'    => [ReturnNoteData::class, P::correction(['issueReasonCode' => '0']), ['issueReasonDescription' => ['The issue reason description field is required.']]],
    'duplicate line ids'           => [ElectronicInvoiceData::class, P::duplicateLineIds('A'),
        ['lines.0.id' => ['The lines.0.id field has a duplicate value.'], 'lines.1.id' => ['The lines.1.id field has a duplicate value.']]],
    'unknown line reference' => [ElectronicInvoiceData::class, F::payload(['lines' => [F::linePayload(['lineReferenceId' => 'Z'])]]),
        ['lines.0.lineReferenceId' => ['The selected lines.0.lineReferenceId is invalid.']]],
    'charge on information line' => [ElectronicInvoiceData::class, P::chargeOnInformationLine(),
        ['lines.1.lineReferenceId' => ['The selected lines.1.lineReferenceId is invalid.']]],
    'unpriced and untaxed invoice line' => [ElectronicInvoiceData::class, F::payload(['lines' => [F::linePayload(['price' => null, 'taxes' => []])]]),
        ['lines.0.price' => ['The lines.0.price field is required.'], 'lines.0.taxes' => ['The lines.0.taxes field is required.']]],
    'invoice with settled payments' => [ElectronicInvoiceData::class, F::payload(['payments' => F::payments()]), ['payments.payments' => ['The payments.payments field is prohibited.']]],
    'invoice receipt with due date' => [ReceiptInvoiceData::class, F::payload(['payments' => [...F::payments(), 'paymentDueDate' => '2026-10-31']]), [
        'payments.paymentDueDate' => ['The payments.payment due date field is prohibited.'],
        'payments.payments'       => ['The payments.payments field prohibits payments.payment due date / payments.payment terms / payments.payee financial accounts from being present.'],
    ]],
    'tax point after issue' => [ElectronicInvoiceData::class, F::payload(['taxPointDate' => '2026-10-03']), ['taxPointDate' => ['The tax point date cannot be later than the issue date.']]],
    'payment before issue'  => [ReceiptInvoiceData::class, F::payload(['payments' => ['payments' => [F::payments()['payments'][0], ['paymentMeansCode' => '10', 'paymentDate' => '2026-10-01']]]]),
        ['payments.payments.1.paymentDate' => ['The payment date must be the issue date.']]],
    'receiver referencing receiver' => [ElectronicInvoiceData::class, F::payload(['receiver' => ['reference' => 'RP']]), ['receiver.reference' => ['The selected receiver.reference is invalid.']]],
    'foreign transport taxpayer'    => [TransportDocumentData::class, P::transport(['receiverTypeCode' => '1', 'receiver' => P::foreignBuyer()]),
        ['receiver.taxId.countryCode' => ['The selected receiver.tax id.country code is invalid.']]],
]);

it('accepts an anonymous sales receipt below the fiscal threshold', function (): void {
    expect(SalesReceiptData::from(P::anonymousSalesReceipt('10000', '1500', '11500'))->receiver)->toBeNull();
});

it('requires an identified receiver on sales receipts from the fiscal threshold', function (): void {
    $payload = P::anonymousSalesReceipt('17400', '2610', '20010');

    expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe(['receiver' => ['The receiver field is required.']]);
    });
});

it('requires an identified receiver on sales receipts whose net plus tax reaches the threshold', function (): void {
    $payload = P::anonymousSalesReceipt('19000', '1000', '20000');

    expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))
        ->toFailValidationOn('receiver', 'The receiver field is required.');
});

it('keeps sales receipts anonymous when only the payable rounding reaches the threshold', function (): void {
    $payload                                    = P::exemptAnonymousSalesReceipt('19999.99');
    $payload['totals']['payableRoundingAmount'] = '0.01';
    $payload['totals']['payableAmount']         = '20000';

    expect(SalesReceiptData::from($payload)->receiver)->toBeNull();
});

it('leaves line taxes optional on credit notes', function (): void {
    $credit = CreditNoteData::from(P::correction(['lines' => [F::linePayload(['taxes' => []])], 'totals' => F::totalsPayload(['taxTotalAmount' => '0', 'payableAmount' => '100'])]));

    expect($credit->lines[0]->taxes)->toBe([]);
});

it('leaves line pricing optional on transport documents', function (): void {
    $route = TransportDocumentData::from(P::transport(['lines' => [F::linePayload(['price' => null, 'priceExtension' => null, 'netTotal' => null, 'taxes' => []])]]));

    expect($route->lines[0]->price)->toBeNull();
});
