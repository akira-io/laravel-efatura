<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Illuminate\Validation\ValidationException;

function assertValidationMessage(callable $callable, string $field, string $message): void
{
    try {
        $callable();
        expect(false)->toBeTrue();
    } catch (ValidationException $validationException) {
        $errors = $validationException->errors();
        expect($errors[$field][0])->toBe($message);
    }
}

function baseInvoicePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type'      => DocumentType::ELECTRONIC_INVOICE,
        'issueDate' => '2026-02-08',
        'emitter'   => [
            'nif'  => '100200300',
            'name' => 'Emitter',
        ],
        'receiver' => [
            'nif'  => '900800700',
            'name' => 'Receiver',
        ],
        'lines' => [
            [
                'description' => 'Item',
                'quantity'    => 1,
                'unitPrice'   => 1000.0,
                'total'       => 1000.0,
                'taxes'       => [
                    [
                        'type'   => 'IVA',
                        'rate'   => 15.0,
                        'amount' => 150.0,
                    ],
                ],
            ],
        ],
        'totals' => [
            'subtotal'   => 1000.0,
            'taxTotal'   => 150.0,
            'grandTotal' => 1150.0,
        ],
    ], $overrides);
}

it('validates invoice issue date', function (): void {
    $payload = baseInvoicePayload(['issueDate' => '']);

    assertValidationMessage(
        fn (): array => InvoiceData::validate($payload),
        'issueDate',
        trans('efatura.invoice.issue_date_required'),
    );
});

it('requires invoice lines', function (): void {
    $payload          = baseInvoicePayload();
    $payload['lines'] = [];

    assertValidationMessage(
        fn (): array => InvoiceData::validate($payload),
        'lines',
        trans('efatura.validation.lines_required'),
    );
});

it('requires receiver for non sales receipt types', function (): void {
    $payload = baseInvoicePayload(['receiver' => null]);

    assertValidationMessage(
        fn (): array => InvoiceData::validate($payload),
        'receiver',
        trans('efatura.invoice.receiver_required_for_type'),
    );
});

it('accepts supported document types', function (): void {
    $policy = app(DocumentTypePolicy::class);

    foreach (DocumentType::cases() as $type) {
        if (! $policy->supportsEmission($type)) {
            continue;
        }

        $payload = baseInvoicePayload(['type' => $type]);

        expect(fn (): array => InvoiceData::validate($payload))
            ->not->toThrow(ValidationException::class);
    }
});

it('rejects unsupported document types', function (): void {
    $unsupported = [
        DocumentType::ELECTRONIC_RECEIPT,
        DocumentType::ELECTRONIC_DEBIT_NOTE,
        DocumentType::ELECTRONIC_RETURN_NOTE,
        DocumentType::ELECTRONIC_ENTRY_NOTE,
    ];

    foreach ($unsupported as $type) {
        $payload = baseInvoicePayload(['type' => $type]);

        assertValidationMessage(
            fn (): array => InvoiceData::validate($payload),
            'type',
            trans('efatura.invoice.document_type_not_supported', ['type' => $type->value]),
        );
    }
});

it('allows sales receipt without receiver below threshold', function (): void {
    $payload = baseInvoicePayload([
        'type'     => DocumentType::ELECTRONIC_SALES_TICKET,
        'receiver' => null,
        'totals'   => [
            'subtotal'   => 1000.0,
            'taxTotal'   => 150.0,
            'grandTotal' => 19000.0,
        ],
    ]);

    expect(fn (): array => SalesReceiptData::validate(['invoice' => $payload]))
        ->not->toThrow(ValidationException::class);
});

it('requires receiver for sales receipt at threshold', function (): void {
    $payload = baseInvoicePayload([
        'type'     => DocumentType::ELECTRONIC_SALES_TICKET,
        'receiver' => null,
        'totals'   => [
            'subtotal'   => 18000.0,
            'taxTotal'   => 2000.0,
            'grandTotal' => 20000.0,
        ],
    ]);

    assertValidationMessage(
        fn (): array => SalesReceiptData::validate(['invoice' => $payload]),
        'invoice.receiver',
        trans('efatura.invoice.receiver_required_for_type'),
    );
});

it('requires credit note references', function (): void {
    $payload = baseInvoicePayload([
        'type'             => DocumentType::ELECTRONIC_CREDIT_NOTE,
        'originalIud'      => '',
        'creditNoteReason' => '',
    ]);

    assertValidationMessage(
        fn (): array => CreditNoteData::validate(['invoice' => $payload]),
        'invoice.originalIud',
        trans('efatura.invoice.original_iud_required'),
    );
});

it('requires NA tax exemption reason', function (): void {
    $payload = [
        'type'            => 'NA',
        'rate'            => 0.0,
        'amount'          => 0.0,
        'exemptionReason' => null,
    ];

    assertValidationMessage(
        fn (): array => TaxData::validate($payload),
        'exemptionReason',
        trans('efatura.validation.na_tax_exemption_required'),
    );
});

it('rejects negative totals', function (): void {
    $payload = [
        'subtotal'   => -1.0,
        'taxTotal'   => 0.0,
        'grandTotal' => 0.0,
    ];

    assertValidationMessage(
        fn (): array => TotalsData::validate($payload),
        'subtotal',
        trans('efatura.validation.totals_negative'),
    );
});

it('requires party fields', function (): void {
    $payload = [
        'nif'  => '',
        'name' => '',
    ];

    assertValidationMessage(
        fn (): array => PartyData::validate($payload),
        'nif',
        trans('efatura.validation.party_nif_required'),
    );
});

it('rejects invoice type mismatch in wrappers', function (): void {
    $payload = baseInvoicePayload(['type' => DocumentType::ELECTRONIC_INVOICE_RECEIPT]);

    assertValidationMessage(
        fn (): array => ElectronicInvoiceData::validate(['invoice' => $payload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );

    $receiptPayload = baseInvoicePayload(['type' => DocumentType::ELECTRONIC_INVOICE]);

    assertValidationMessage(
        fn (): array => ReceiptInvoiceData::validate(['invoice' => $receiptPayload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );

    $transportPayload = baseInvoicePayload(['type' => DocumentType::ELECTRONIC_INVOICE]);

    assertValidationMessage(
        fn (): array => TransportDocumentData::validate(['invoice' => $transportPayload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );
});
