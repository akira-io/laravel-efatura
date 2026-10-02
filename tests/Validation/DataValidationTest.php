<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Support\DefaultDocumentTypePolicy;
use Akira\Efatura\Tests\Support\ValidationFixtures;
use Brick\Math\BigDecimal;
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
    $line = new LineItemData(new QuantityData(BigDecimal::of('1'), 'C62'), new ItemData('Item', 'SKU-1'), price: FiscalMoney::cve('100'), netTotal: FiscalMoney::cve('100'));

    expect($line->item->description)->toBe('Item');
});

it('covers policy behavior', function (): void {
    $policy = new DefaultDocumentTypePolicy;

    expect($policy->supportsEmission(DocumentType::Invoice))->toBeTrue()
        ->and($policy->supportsEmission(DocumentType::RegistrationNote))->toBeFalse()
        ->and($policy->allowsIud(DocumentType::Invoice))->toBeTrue()
        ->and($policy->allowsIud(DocumentType::RegistrationNote))->toBeFalse()
        ->and($policy->allowsXml(DocumentType::Invoice))->toBeTrue()
        ->and($policy->allowsXml(DocumentType::RegistrationNote))->toBeFalse()
        ->and($policy->allowedInProduction(DocumentType::Invoice))->toBeTrue()
        ->and($policy->allowedInProduction(DocumentType::RegistrationNote))->toBeFalse();
});

it('covers tax data constructor', function (): void {
    $tax = new TaxData(TaxType::ValueAddedTax, taxPercentage: BigDecimal::of('15'), taxTotal: FiscalMoney::cve('150'));

    expect($tax->taxTypeCode)->toBe(TaxType::ValueAddedTax)
        ->and((string) $tax->taxPercentage)->toBe('15')
        ->and((string) $tax->taxTotal->getAmount())->toBe('150.00')
        ->and($tax->taxExemptionReasonCode)->toBeNull();
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
        DocumentType::Invoice,
        '2026-02-08',
        new PartyData(new TaxIdData('100200300', 'CV'), 'Emitter'),
        new PartyData(new TaxIdData('900800700', 'CV'), 'Receiver'),
        [new LineItemData(new QuantityData(BigDecimal::of('1'), 'C62'), new ItemData('Item', 'SKU-1'), price: FiscalMoney::cve('100'), netTotal: FiscalMoney::cve('100'))],
        new TotalsData(FiscalMoney::cve('100'), FiscalMoney::cve('100'), FiscalMoney::cve('0'), FiscalMoney::cve('100')),
    );

    expect($invoice->type)->toBe(DocumentType::Invoice);
});

it('rejects unsupported document types', function (): void {
    $unsupported = [
        DocumentType::Receipt,
        DocumentType::DebitNote,
        DocumentType::ReturnNote,
        DocumentType::RegistrationNote,
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
    $data = ValidationFixtures::invoicePayload(['receiver' => 'invalid']);

    $validator = resolve('validator')->make($data, []);
    InvoiceData::withValidator($validator);
    $validator->passes();

    $errors = $validator->errors()->toArray();

    expect($errors['receiver'][0])->toBe(trans('efatura.validation.receiver_required'));
});

it('covers invoice lines branch', function (): void {
    $data          = ValidationFixtures::invoicePayload();
    $data['lines'] = [];

    $validator = resolve('validator')->make($data, []);
    InvoiceData::withValidator($validator);
    $validator->passes();

    expect($validator->errors()->toArray())->toHaveKey('lines');
});
