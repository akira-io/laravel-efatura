<?php

declare(strict_types=1);
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('resolves conditional fields relative to their nested owning data', function (): void {
    expect(ElectronicInvoiceData::validateAndCreate(P::nestedEvidenceInvoice())->receiver->reference->value)->toBe('EP');
});

it('round trips the canonical document including null optional nested values', function (): void {
    $document = ElectronicInvoiceData::from(F::payload());

    expect(ElectronicInvoiceData::validateAndCreate($document->toArray())->toArray())->toBe($document->toArray());
});

it('reports nested dependent rule failures at the owning fiscal path', function (string $path, mixed $value, string $field, string $message): void {
    $payload = F::payload();
    Arr::set($payload, $path, $value);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::validateAndCreate($payload))->toFailValidationOn($field, $message);
})->with([
    'reference beside receiver identity' => ['receiver.reference', 'EP', 'receiver.reference',
        'The receiver.reference field prohibits receiver.tax id / receiver.name / receiver.address / receiver.contacts from being present.'],
    'stamp tax without code'    => ['lines.0.taxes', [['taxTypeCode' => 'IS', 'taxPercentage' => '1']], 'lines.0.taxes.0.stampTaxCode', 'The lines.0.taxes.0.stamp tax code field is required.'],
    'exempt tax without reason' => ['lines.0.taxes', [['taxTypeCode' => 'NA', 'taxPercentage' => '1']], 'lines.0.taxes.0.taxExemptionReasonCode',
        'The lines.0.taxes.0.tax exemption reason code field is required.'],
    'reference without document' => ['references', [['innerDocumentNumber' => 'REF']], 'references.0.fiscalDocument',
        'The references.0.fiscal document field is required when none of references.0.payment amount / references.0.taxes are present.'],
    'due date with payments' => ['payments', ['paymentDueDate' => '2026-10-31', 'payments' => [['paymentAmount' => '100']]], 'payments.payments',
        'The payments.payments field is prohibited.'],
    'charge without target'        => ['lines.0.lineTypeCode', 'C', 'lines.0.lineReferenceId', 'The lines.0.lineReferenceId field is required.'],
    'two standard identifications' => ['lines.0.item.standardIdentification', ['ean' => '123', 'gtin' => '456'], 'lines.0.item.standardIdentification.ean',
        'The lines.0.item.standard identification.ean field prohibits lines.0.item.standard identification.gtin / '
        . 'lines.0.item.standard identification.upc / lines.0.item.standard identification.pharmacode from being present.'],
    'two bank account choices' => ['payments', ['payeeFinancialAccounts' => [['name' => 'Bank Account', 'nib' => '123456789012345678901', 'accountNumber' => '123']]],
        'payments.payeeFinancialAccounts.0.nib',
        'The payments.payee financial accounts.0.nib field prohibits payments.payee financial accounts.0.account number from being present.'],
    'other contingency without text' => ['emission', ['issueMode' => 2, 'contingency' => P::offlineContingency(['reasonTypeCode' => '0'])], 'emission.contingency.reasonDescription',
        'The emission.contingency.reason description field is required.'],
    'national address without code' => ['receiver.address', ['countryCode' => 'CV', 'addressDetail' => 'Praia'], 'receiver.address.addressCode',
        'The receiver.address.address code field is required when receiver.address.country code is CV.'],
]);

it('rejects an unpaired route duration at its route position', function (): void {
    $payload                                                          = P::transport();
    $payload['transportRoute']['locations'][0]['duration']['endDate'] = '2026-10-02';

    expect(fn (): TransportDocumentData => TransportDocumentData::validateAndCreate($payload))
        ->toFailValidationOn(
            'transportRoute.locations.0.duration.endTime',
            'The transport route.locations.0.duration.end time field is required when transport route.locations.0.duration.end date is present.',
        );
});

it('accepts a paired route duration', function (): void {
    $payload                                                          = P::transport();
    $payload['transportRoute']['locations'][0]['duration']['endDate'] = '2026-10-02';
    $payload['transportRoute']['locations'][0]['duration']['endTime'] = '14:00:00';

    expect(TransportDocumentData::validateAndCreate($payload)->transportRoute->locations[0]->duration->endTime->format('H:i:s'))->toBe('14:00:00');
});
