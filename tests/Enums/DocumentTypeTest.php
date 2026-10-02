<?php

declare(strict_types=1);

use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Illuminate\Support\ItemNotFoundException;

it('includes all official document types', function (): void {
    $values = collect(DocumentType::cases())
        ->map(static fn (DocumentType $type): string => $type->value)
        ->sort()
        ->values()
        ->all();

    expect($values)->toBe([
        'DTE',
        'DVE',
        'FRE',
        'FTE',
        'NCE',
        'NDE',
        'NLE',
        'RCE',
        'TVE',
    ]);
});

it('marks supported document types', function (): void {
    foreach (DocumentType::cases() as $type) {
        expect($type)->toBeInstanceOf(DocumentType::class);
    }
});

it('maps each document type to its data class and back', function (DocumentType $type, string $dataClass): void {
    expect($type->dataClass())->toBe($dataClass)
        ->and(DocumentType::fromDataClass($dataClass))->toBe($type)
        ->and($dataClass::documentType())->toBe($type);
})->with([
    [DocumentType::Invoice, ElectronicInvoiceData::class],
    [DocumentType::InvoiceReceipt, ReceiptInvoiceData::class],
    [DocumentType::SalesReceipt, SalesReceiptData::class],
    [DocumentType::Receipt, ReceiptData::class],
    [DocumentType::CreditNote, CreditNoteData::class],
    [DocumentType::DebitNote, DebitNoteData::class],
    [DocumentType::Transport, TransportDocumentData::class],
    [DocumentType::ReturnNote, ReturnNoteData::class],
    [DocumentType::RegistrationNote, RegistrationNoteData::class],
]);

it('rejects a class that is not a document', function (): void {
    expect(fn (): DocumentType => DocumentType::fromDataClass(InvoiceData::class))->toThrow(ItemNotFoundException::class);
});
