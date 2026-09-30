<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\DefaultDocumentTypePolicy;
use Akira\Efatura\Tests\Support\ValidationFixtures;
use Illuminate\Validation\ValidationException;

it('validates invoice issue date', function (): void {
    $payload = ValidationFixtures::invoicePayload(['issueDate' => '']);

    ValidationFixtures::assertMessage(
        fn (): array => InvoiceData::validate($payload),
        'issueDate',
        trans('efatura.invoice.issue_date_required'),
    );
});

it('covers line item construction', function (): void {
    $line = new LineItemData('Item', 1.0, 100.0, 100.0, []);

    expect($line->description)->toBe('Item');
});

it('covers policy behavior', function (): void {
    $policy = new DefaultDocumentTypePolicy;

    expect($policy->supportsEmission(DocumentType::ELECTRONIC_INVOICE))->toBeTrue()
        ->and($policy->supportsEmission(DocumentType::ELECTRONIC_ENTRY_NOTE))->toBeFalse()
        ->and($policy->allowsIud(DocumentType::ELECTRONIC_INVOICE))->toBeTrue()
        ->and($policy->allowsIud(DocumentType::ELECTRONIC_ENTRY_NOTE))->toBeFalse()
        ->and($policy->allowsXml(DocumentType::ELECTRONIC_INVOICE))->toBeTrue()
        ->and($policy->allowsXml(DocumentType::ELECTRONIC_ENTRY_NOTE))->toBeFalse()
        ->and($policy->allowedInProduction(DocumentType::ELECTRONIC_INVOICE))->toBeTrue()
        ->and($policy->allowedInProduction(DocumentType::ELECTRONIC_ENTRY_NOTE))->toBeFalse();
});

it('covers party rules and messages', function (): void {
    expect(PartyData::rules())->toHaveKey('nif')
        ->and(PartyData::messages())->toHaveKey('nif.required')
        ->and(PartyData::stopOnFirstFailure())->toBeTrue();
});

it('covers tax rules and messages', function (): void {
    expect(TaxData::rules())->toHaveKey('exemptionReason')
        ->and(TaxData::messages())->toHaveKey('exemptionReason.required_if')
        ->and(TaxData::stopOnFirstFailure())->toBeTrue();
});

it('covers tax data constructor', function (): void {
    $tax = new TaxData('IVA', 15.0, 150.0);

    expect($tax->type)->toBe('IVA')
        ->and($tax->rate)->toBe(15.0)
        ->and($tax->amount)->toBe(150.0)
        ->and($tax->exemptionReason)->toBeNull();
});

it('covers totals rules and messages', function (): void {
    expect(TotalsData::rules())->toHaveKey('subtotal')
        ->and(TotalsData::messages())->toHaveKey('subtotal.min')
        ->and(TotalsData::stopOnFirstFailure())->toBeTrue();
});

it('requires invoice lines', function (): void {
    $payload          = ValidationFixtures::invoicePayload();
    $payload['lines'] = [];

    ValidationFixtures::assertMessage(
        fn (): array => InvoiceData::validate($payload),
        'lines',
        trans('efatura.validation.lines_required'),
    );
});

it('requires receiver for non sales receipt types', function (): void {
    $payload = ValidationFixtures::invoicePayload(['receiver' => null]);

    ValidationFixtures::assertMessage(
        fn (): array => InvoiceData::validate($payload),
        'receiver',
        trans('efatura.invoice.receiver_required_for_type'),
    );
});

it('accepts supported document types', function (): void {
    $policy = resolve(DocumentTypePolicy::class);

    foreach (DocumentType::cases() as $type) {
        if (! $policy->supportsEmission($type)) {
            continue;
        }

        $payload = ValidationFixtures::invoicePayload(['type' => $type]);

        expect(fn (): array => InvoiceData::validate($payload))
            ->not->toThrow(ValidationException::class);
    }
});

it('covers invoice data constructor', function (): void {
    $invoice = new InvoiceData(
        DocumentType::ELECTRONIC_INVOICE,
        '2026-02-08',
        new PartyData('100200300', 'Emitter'),
        new PartyData('900800700', 'Receiver'),
        [new LineItemData('Item', 1.0, 100.0, 100.0, [])],
        new TotalsData(100.0, 0.0, 100.0),
    );

    expect($invoice->type)->toBe(DocumentType::ELECTRONIC_INVOICE);
});

it('rejects unsupported document types', function (): void {
    $unsupported = [
        DocumentType::ELECTRONIC_RECEIPT,
        DocumentType::ELECTRONIC_DEBIT_NOTE,
        DocumentType::ELECTRONIC_RETURN_NOTE,
        DocumentType::ELECTRONIC_ENTRY_NOTE,
    ];

    foreach ($unsupported as $type) {
        $payload = ValidationFixtures::invoicePayload(['type' => $type]);

        ValidationFixtures::assertMessage(
            fn (): array => InvoiceData::validate($payload),
            'type',
            trans('efatura.invoice.document_type_not_supported', ['type' => $type->value]),
        );
    }
});

it('covers invoice type non string branch', function (): void {
    $validator = resolve('validator')->make(['type' => 123], []);
    InvoiceData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->isEmpty())->toBeTrue();
});

it('covers invoice type invalid string branch', function (): void {
    $data = [
        'type' => 'INVALID',
    ];

    $validator = resolve('validator')->make($data, []);
    InvoiceData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->isEmpty())->toBeTrue();
});

it('covers receiver invalid type branch', function (): void {
    $data = [
        'type'      => DocumentType::ELECTRONIC_INVOICE,
        'issueDate' => '2026-02-08',
        'emitter'   => [
            'nif'  => '100200300',
            'name' => 'Emitter',
        ],
        'receiver' => 'invalid',
        'lines'    => [
            [
                'description' => 'Item',
                'quantity'    => 1,
                'unitPrice'   => 1000.0,
                'total'       => 1000.0,
                'taxes'       => [],
            ],
        ],
        'totals' => [
            'subtotal'   => 1000.0,
            'taxTotal'   => 0.0,
            'grandTotal' => 1000.0,
        ],
    ];

    $validator = resolve('validator')->make($data, []);
    InvoiceData::withValidator($validator);
    $validator->passes();

    $errors = $validator->errors()->toArray();

    expect($errors['receiver'][0])->toBe(trans('efatura.validation.receiver_required'));
});

it('covers invoice lines branch', function (): void {
    $data = [
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
        'lines'  => [],
        'totals' => [
            'subtotal'   => 1000.0,
            'taxTotal'   => 0.0,
            'grandTotal' => 1000.0,
        ],
    ];

    $validator = resolve('validator')->make($data, []);
    InvoiceData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->toArray())->toHaveKey('lines');
});
