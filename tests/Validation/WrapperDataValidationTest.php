<?php

declare(strict_types=1);

use Akira\Efatura\Concerns\ValidatesInvoiceType;
use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Tests\Support\ValidationFixtures;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

it('covers sales receipt receiver type branch', function (): void {
    $data = [
        'invoice' => [
            'type'   => DocumentType::SalesReceipt,
            'totals' => [
                'payableAmount' => '20000',
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
        new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter'),
        new PartyData(new TaxIdData('900800700', 'CV'), 'Receiver'),
        [new LineItemData(new QuantityData(BigDecimal::of('1'), 'C62'), new ItemData('Item', 'SKU-1'), price: FiscalMoney::cve('100'), netTotal: FiscalMoney::cve('100'))],
        new TotalsData(FiscalMoney::cve('100'), FiscalMoney::cve('100'), FiscalMoney::cve('0'), FiscalMoney::cve('100')),
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
            'netTotalAmount' => '1000',
            'taxTotalAmount' => '150',
            'payableAmount'  => '19000',
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
            'netTotalAmount' => '18000',
            'taxTotalAmount' => '2000',
            'payableAmount'  => '20000',
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
    expect(fn (): TaxData => new TaxData(TaxType::NotApplicable))->toThrow(ValidationException::class);
});

it('rejects negative totals', function (): void {
    expect(fn (): TotalsData => new TotalsData(FiscalMoney::cve('-1'), FiscalMoney::cve('0'), FiscalMoney::cve('0'), FiscalMoney::cve('0')))->toThrow(ValidationException::class);
});

it('requires party fields', function (): void {
    expect(fn (): PartyData => new PartyData)->toThrow(ValidationException::class);
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
