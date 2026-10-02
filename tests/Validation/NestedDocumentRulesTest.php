<?php

declare(strict_types=1);
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});
afterEach(fn () => CarbonImmutable::setTestNow());
it('resolves conditional fields relative to their nested owning data', function (): void {
    $payload = F::payload(['receiver' => ['reference' => 'EP'],
        'lines'                       => [F::linePayload(['taxes' => [['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1']],
            'item'                                                => ['description' => 'Product', 'emitterIdentification' => 'SKU', 'standardIdentification' => ['ean' => '123456789']]])],
        'totals'     => F::totalsPayload(['taxTotalAmount' => '0', 'payableAmount' => '100']),
        'references' => [['paymentAmount' => '100']],
        'payments'   => ['payeeFinancialAccounts' => [['name' => 'Bank Account', 'nib' => '123456789012345678901']]],
        'emission'   => ['issueMode' => 2, 'contingency' => ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'reasonTypeCode' => '0', 'reasonDescription' => 'Temporary service interruption']],
    ]);
    expect(ElectronicInvoiceData::validateAndCreate($payload)->receiver->reference->value)->toBe('EP');
});

it('round trips the canonical document including null optional nested values', function (): void {
    $document = ElectronicInvoiceData::from(F::payload());
    expect(ElectronicInvoiceData::validateAndCreate($document->toArray())->toArray())->toBe($document->toArray());
});

it('reports nested dependent rule failures at the owning fiscal path', function (array $changes, string $field): void {
    $payload = F::payload();
    foreach ($changes as $path => $value) {
        Arr::set($payload, $path, $value);
    }

    try {
        ElectronicInvoiceData::validateAndCreate($payload);
        test()->fail('Nested invalid evidence accepted');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey($field);
    }
})->with([
    [['receiver.reference' => 'EP'], 'receiver.reference'],
    [['lines.0.taxes' => [['taxTypeCode' => 'IS', 'taxPercentage' => '1']]], 'lines.0.taxes.0.stampTaxCode'],
    [['lines.0.taxes' => [['taxTypeCode' => 'NA', 'taxPercentage' => '1']]], 'lines.0.taxes.0.taxExemptionReasonCode'],
    [['references' => [['innerDocumentNumber' => 'REF']]], 'references.0.fiscalDocument'],
    [['payments' => ['paymentDueDate' => '2026-10-31', 'payments' => [['paymentAmount' => '100']]]], 'payments.payments'],
    [['lines.0.lineTypeCode' => 'C'], 'lines.0.lineReferenceId'],
    [['lines.0.item.standardIdentification' => ['ean' => '123', 'gtin' => '456']], 'lines.0.item.standardIdentification.ean'],
    [['payments' => ['payeeFinancialAccounts' => [['name' => 'Bank Account', 'nib' => '123456789012345678901', 'accountNumber' => '123']]]], 'payments.payeeFinancialAccounts.0.nib'],
    [['emission' => ['issueMode' => 2, 'contingency' => ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'reasonTypeCode' => '0']]], 'emission.contingency.reasonDescription'],
    [['receiver.address' => ['countryCode' => 'CV', 'addressDetail' => 'Praia']], 'receiver.address.addressCode'],
]);

it('validates duration pairs at their route position', function (): void {
    $payload = F::payload(['transportDocumentTypeCode' => '2', 'transportServiceProvider' => ['reference' => 'EP'], 'transportRoute' => F::route()]);
    unset($payload['totals']);
    $payload['transportRoute']['locations'][0]['duration']['endDate'] = '2026-10-02';

    try {
        TransportDocumentData::validateAndCreate($payload);
        test()->fail('Unpaired duration accepted');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('transportRoute.locations.0.duration.endTime');
    }

    $payload['transportRoute']['locations'][0]['duration']['endTime'] = '14:00:00';
    expect(TransportDocumentData::validateAndCreate($payload)->transportRoute->locations[0]->duration->endTime->format('H:i:s'))->toBe('14:00:00');
});
