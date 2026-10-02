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
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;

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
    expect(fn (): DocumentType => DocumentType::fromDataClass(DocumentData::class))->toThrow(ItemNotFoundException::class);
});

it('names exactly the document elements the XSD admits in a Dfe', function (): void {
    $schema = new DOMDocument;
    $schema->load(dirname(__DIR__, 2) . '/resources/xsd/efatura/2024-05-27/common/CV_EFatura_MainTypes_v1.0.xsd');

    $xpath = new DOMXPath($schema);
    $xpath->registerNamespace('x', 'http://www.w3.org/2001/XMLSchema');

    $elements = collect($xpath->query('//x:complexType[@name="ctDfe"]//x:choice/x:element/@ref'))
        ->map(static fn (DOMAttr $reference): string => Str::after($reference->value, ':'))
        ->sort()
        ->values()
        ->all();

    expect(collect(DocumentType::cases())->map(static fn (DocumentType $type): string => $type->xmlElement())->sort()->values()->all())
        ->toBe($elements)
        ->and($elements)->toHaveCount(9);
});
