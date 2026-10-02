<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

function correctionPayload(array $overrides = []): array
{
    return F::payload(['issueReasonCode' => '2', 'references' => F::references(), ...$overrides]);
}

function transportPayload(array $overrides = []): array
{
    $payload = F::payload(['transportDocumentTypeCode' => '2', 'transportServiceProvider' => ['reference' => 'EP'], 'transportRoute' => F::route(), ...$overrides]);
    unset($payload['totals']);

    return $payload;
}

it('allows each correction document only its own issue reasons', function (): void {
    expect(IssueReason::allowedFor(DocumentType::CreditNote))->toContain(IssueReason::RappelDiscount, IssueReason::Article65Paragraph7)->not->toContain(IssueReason::ExpenseDebit, IssueReason::Other)
        ->and(IssueReason::allowedFor(DocumentType::DebitNote))->toContain(IssueReason::ExpenseDebit, IssueReason::Article65Paragraph4)->not->toContain(IssueReason::RappelDiscount)
        ->and(IssueReason::allowedFor(DocumentType::ReturnNote))->toContain(IssueReason::Other, IssueReason::Article65Paragraph7)->not->toContain(IssueReason::ExpenseDebit)
        ->and(IssueReason::allowedFor(DocumentType::Invoice))->toBe([]);
});

it('derives the line policy from the document type', function (): void {
    expect(DocumentType::Transport->requiresLinePricing())->toBeFalse()
        ->and(DocumentType::CreditNote->requiresLinePricing())->toBeTrue()
        ->and(DocumentType::CreditNote->requiresLineTaxes())->toBeFalse()
        ->and(DocumentType::ReturnNote->requiresLineTaxes())->toBeFalse()
        ->and(DocumentType::Transport->requiresLineTaxes())->toBeFalse()
        ->and(DocumentType::Invoice->requiresLineTaxes())->toBeTrue();
});

it('reports document compatibility failures at their full path', function (Closure $create, array $errors): void {
    expect($create)->toThrow(function (ValidationException $exception) use ($errors): void {
        expect($exception->errors())->toBe($errors);
    });
})->with([
    'issue reason of another type' => [fn (): CreditNoteData => CreditNoteData::from(correctionPayload(['issueReasonCode' => '4'])), ['issueReasonCode' => ['The selected issue reason code is invalid.']]],
    'missing references'           => [fn (): CreditNoteData => CreditNoteData::from(correctionPayload(['references' => []])), ['references' => ['The references field is required.']]],
    'other reason without text'    => [fn (): ReturnNoteData => ReturnNoteData::from(correctionPayload(['issueReasonCode' => '0'])), ['issueReasonDescription' => ['The issue reason description field is required.']]],
    'duplicate line ids'           => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => [F::linePayload(['id' => 'A']), F::linePayload(['id' => 'A'])]])),
        ['lines.0.id' => ['The lines.0.id field has a duplicate value.'], 'lines.1.id' => ['The lines.1.id field has a duplicate value.']]],
    'unknown line reference' => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => [F::linePayload(['lineReferenceId' => 'Z'])]])),
        ['lines.0.lineReferenceId' => ['The selected lines.0.lineReferenceId is invalid.']]],
    'charge on information line' => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => [F::linePayload(['id' => 'A', 'lineTypeCode' => 'I']), F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => 'A'])]])),
        ['lines.1.lineReferenceId' => ['The selected lines.1.lineReferenceId is invalid.']]],
    'unpriced and untaxed invoice line' => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['lines' => [F::linePayload(['price' => null, 'taxes' => []])]])),
        ['lines.0.price' => ['The lines.0.price field is required.'], 'lines.0.taxes' => ['The lines.0.taxes field is required.']]],
    'invoice with settled payments' => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['payments' => F::payments()])), ['payments.payments' => ['The payments.payments field is prohibited.']]],
    'invoice receipt with due date' => [fn (): ReceiptInvoiceData => ReceiptInvoiceData::from(F::payload(['payments' => [...F::payments(), 'paymentDueDate' => '2026-10-31']])), [
        'payments.paymentDueDate' => ['The payments.payment due date field is prohibited.'],
        'payments.payments'       => ['The payments.payments field prohibits payments.payment due date / payments.payment terms / payments.payee financial accounts from being present.'],
    ]],
    'tax point after issue' => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['taxPointDate' => '2026-10-03'])), ['taxPointDate' => ['The tax point date cannot be later than the issue date.']]],
    'payment before issue'  => [fn (): ReceiptInvoiceData => ReceiptInvoiceData::from(F::payload(['payments' => ['payments' => [['paymentMeansCode' => '10', 'paymentAmount' => '115'], ['paymentMeansCode' => '10', 'paymentDate' => '2026-10-01']]]])),
        ['payments.payments.1.paymentDate' => ['The payment date must be the issue date.']]],
    'receiver referencing receiver' => [fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['receiver' => ['reference' => 'RP']])), ['receiver.reference' => ['The selected receiver.reference is invalid.']]],
    'foreign transport taxpayer'    => [fn (): TransportDocumentData => TransportDocumentData::from(transportPayload(['receiverTypeCode' => '1', 'receiver' => ['taxId' => ['value' => '123456789', 'countryCode' => 'PT'], 'name' => 'Foreign buyer']])),
        ['receiver.taxId.countryCode' => ['The selected receiver.tax id.country code is invalid.']]],
]);

it('requires an identified receiver on sales receipts from the fiscal threshold', function (string $amount, string $tax, string $payable, bool $required): void {
    $payload = F::payload(['receiver' => null, 'payments' => F::payments(),
        'lines'                       => [F::linePayload(['price' => $amount, 'priceExtension' => $amount, 'netTotal' => $amount])],
        'totals'                      => F::totalsPayload(['priceExtensionTotalAmount' => $amount, 'netTotalAmount' => $amount, 'taxTotalAmount' => $tax, 'payableAmount' => $payable])]);
    if ($required) {
        expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['receiver' => ['The receiver field is required.']]);
        });
    } else {
        expect(SalesReceiptData::from($payload)->receiver)->toBeNull();
    }
})->with([['10000', '1500', '11500', false], ['17400', '2610', '20010', true]]);

it('leaves pricing and taxes optional where the document type does not require them', function (): void {
    $credit = CreditNoteData::from(correctionPayload(['lines' => [F::linePayload(['taxes' => []])], 'totals' => F::totalsPayload(['taxTotalAmount' => '0', 'payableAmount' => '100'])]));
    $route  = TransportDocumentData::from(transportPayload(['lines' => [F::linePayload(['price' => null, 'priceExtension' => null, 'netTotal' => null, 'taxes' => []])]]));

    expect($credit->lines[0]->taxes)->toBe([])->and($route->lines[0]->price)->toBeNull();
});
