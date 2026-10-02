<?php

declare(strict_types=1);

use Akira\Efatura\Concerns\ValidatesInvoiceType;
use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Tests\Support\ValidationFixtures;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

it('covers sales receipt receiver type branch', function (): void {
    $data = [
        'invoice' => [
            'type'   => DocumentType::SalesReceipt,
            'totals' => [
                'grandTotal' => 20000.0,
            ],
            'receiver' => 'invalid',
        ],
    ];

    $validator = resolve('validator')->make($data, []);
    SalesReceiptData::withValidator($validator);
    $validator->passes();

    $errors = $validator->errors()->toArray();

    expect($errors['invoice.receiver'][0])->toBe(trans('efatura.validation.receiver_required'));
});

it('covers sales receipt early return branches', function (): void {
    $validator = resolve('validator')->make([], ['invoice' => ['required']]);
    SalesReceiptData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->isNotEmpty())->toBeTrue();

    $validator = resolve('validator')->make(['invoice' => ['type' => 'FTE']], []);
    SalesReceiptData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->toArray())->toHaveKey('invoice.type');
});

it('covers credit note type validator branch', function (): void {
    $data = [
        'invoice' => [
            'type' => DocumentType::CreditNote,
        ],
    ];

    $validator = resolve('validator')->make($data, []);
    CreditNoteData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->isNotEmpty())->toBeFalse();
});

it('covers wrapper constructors', function (): void {
    $invoice = new InvoiceData(
        DocumentType::Invoice,
        '2026-02-08',
        new PartyData('100200300', 'Emitter'),
        new PartyData('900800700', 'Receiver'),
        [new LineItemData('Item', 1.0, 100.0, 100.0, [])],
        new TotalsData(100.0, 0.0, 100.0),
    );

    expect(new ElectronicInvoiceData($invoice))->toBeInstanceOf(ElectronicInvoiceData::class)
        ->and(new ReceiptInvoiceData($invoice))->toBeInstanceOf(ReceiptInvoiceData::class)
        ->and(new SalesReceiptData($invoice))->toBeInstanceOf(SalesReceiptData::class)
        ->and(new CreditNoteData($invoice))->toBeInstanceOf(CreditNoteData::class)
        ->and(new TransportDocumentData($invoice))->toBeInstanceOf(TransportDocumentData::class);
});

it('covers wrapper validation early return', function (): void {
    foreach ([
        ElectronicInvoiceData::class,
        ReceiptInvoiceData::class,
        TransportDocumentData::class,
    ] as $wrapper) {
        try {
            $wrapper::validate([]);
            expect(false)->toBeTrue();
        } catch (ValidationException $validationException) {
            expect($validationException->errors())->toHaveKey('invoice');
        }
    }
});

it('covers credit note type mismatch', function (): void {
    $payload = ValidationFixtures::invoicePayload([
        'type'             => DocumentType::Invoice,
        'originalIud'      => 'ORI-12345',
        'creditNoteReason' => 'Adjustment',
    ]);

    ValidationFixtures::assertMessage(
        fn (): array => CreditNoteData::validate(['invoice' => $payload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );
});

it('covers invoice type trait with invalid value', function (): void {
    $tester = new class
    {
        use ValidatesInvoiceType;

        public static function run(Validator $validator, DocumentType $expected, mixed $value, string $path): void
        {
            self::ensureInvoiceType($validator, $expected, $value, $path);
        }
    };

    $validator = resolve('validator')->make(['type' => 123], []);
    $tester::run($validator, DocumentType::Invoice, 123, 'type');

    expect($validator->errors()->toArray())->toHaveKey('type');
});

it('allows sales receipt without receiver below threshold', function (): void {
    $payload = ValidationFixtures::invoicePayload([
        'type'     => DocumentType::SalesReceipt,
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
    $payload = ValidationFixtures::invoicePayload([
        'type'     => DocumentType::SalesReceipt,
        'receiver' => null,
        'totals'   => [
            'subtotal'   => 18000.0,
            'taxTotal'   => 2000.0,
            'grandTotal' => 20000.0,
        ],
    ]);

    ValidationFixtures::assertMessage(
        fn (): array => SalesReceiptData::validate(['invoice' => $payload]),
        'invoice.receiver',
        trans('efatura.invoice.receiver_required_for_type'),
    );
});

it('requires credit note references', function (): void {
    $payload = ValidationFixtures::invoicePayload([
        'type'             => DocumentType::CreditNote,
        'originalIud'      => '',
        'creditNoteReason' => '',
    ]);

    ValidationFixtures::assertMessage(
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

    ValidationFixtures::assertMessage(
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

    ValidationFixtures::assertMessage(
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

    ValidationFixtures::assertMessage(
        fn (): array => PartyData::validate($payload),
        'nif',
        trans('efatura.validation.party_nif_required'),
    );
});

it('rejects invoice type mismatch in wrappers', function (): void {
    $payload = ValidationFixtures::invoicePayload(['type' => DocumentType::InvoiceReceipt]);

    ValidationFixtures::assertMessage(
        fn (): array => ElectronicInvoiceData::validate(['invoice' => $payload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );

    $receiptPayload = ValidationFixtures::invoicePayload(['type' => DocumentType::Invoice]);

    ValidationFixtures::assertMessage(
        fn (): array => ReceiptInvoiceData::validate(['invoice' => $receiptPayload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );

    $transportPayload = ValidationFixtures::invoicePayload(['type' => DocumentType::Invoice]);

    ValidationFixtures::assertMessage(
        fn (): array => TransportDocumentData::validate(['invoice' => $transportPayload]),
        'invoice.type',
        trans('efatura.validation.invoice_type_mismatch'),
    );
});
