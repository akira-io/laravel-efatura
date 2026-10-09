<?php

declare(strict_types=1);
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('rejects a document graph assembled from data objects without lines', function (): void {
    $graph = P::dataObjectGraph();

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($graph))
        ->toFailValidationOn('lines', 'The lines field must have at least 1 items.');
});

it('accepts a document graph assembled from data objects', function (): void {
    expect(ElectronicInvoiceData::from([...P::dataObjectGraph(), 'lines' => [F::line()]])->lines)->toHaveCount(1);
});

it('requires the rent section on rent receipts', function (): void {
    $payload = F::receiptPayload('4');

    expect(fn (): ReceiptData => ReceiptData::from($payload))
        ->toFailValidationOn('rentReceipt', 'The rent receipt field is required.');
});

it('accepts a rent receipt without invoice sections', function (): void {
    expect(ReceiptData::from([...F::receiptPayload('4'), 'rentReceipt' => P::rentReceipt()])->rentReceipt->assetId)->toBe('HOUSE');
});

it('rejects invoice lines on rent receipts', function (): void {
    $payload = [...F::receiptPayload('4'), 'rentReceipt' => P::rentReceipt(), 'lines' => []];

    expect(fn (): ReceiptData => ReceiptData::from($payload))
        ->toFailValidationOn('lines', 'This field does not belong to this fiscal document type.');
});

it('rejects a receiver on transport documents for global receivers', function (): void {
    $payload = P::transport(['receiverTypeCode' => '3']);

    expect(fn (): TransportDocumentData => TransportDocumentData::from($payload))
        ->toFailValidationOn('receiver', 'The receiver field is prohibited.');
});

it('rejects totals on transport documents', function (): void {
    $payload = [...P::transport(['receiverTypeCode' => '3', 'receiver' => null]), 'totals' => F::totalsPayload()];

    expect(fn (): TransportDocumentData => TransportDocumentData::from($payload))
        ->toFailValidationOn('totals', 'This field does not belong to this fiscal document type.');
});

it('preserves self billing authorization', function (): void {
    expect(ElectronicInvoiceData::from(P::selfBilled(F::payload()))->header->selfBilling->authorizationCode)->toBe('1234');
});

it('accepts issuance at the inclusive online date window edges in Cabo Verde time', function (string $date, string $time): void {
    expect(B::issuance(P::header(['issueDate' => $date, 'issueTime' => $time]))->build()->header->issueDate->format('Y-m-d'))->toBe($date);
})->with([
    'latest instant'   => ['2026-10-02', '13:00:00'],
    'earliest instant' => ['2026-10-01', '12:00:00'],
]);

it('rejects issuance outside the online date window in Cabo Verde time', function (string $date, string $time): void {
    $draft = B::issuance(P::header(['issueDate' => $date, 'issueTime' => $time]));

    expect(fn (): DocumentData => $draft->build())->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe(['header.issueDate' => ['The issue date and time are outside the permitted emission window.']]);
    });
})->with([
    'one second late'  => ['2026-10-02', '13:00:01'],
    'one second early' => ['2026-10-01', '11:59:59'],
]);

it('accepts mode compatible contingency evidence', function (int $mode, array $contingency): void {
    expect(ElectronicInvoiceData::from(F::payload(['emission' => ['issueMode' => $mode, 'contingency' => $contingency]]))->emission->issueMode)->toBe(EmissionMode::from($mode));
})->with([
    'offline with time'    => [2, P::offlineContingency()],
    'off with unique code' => [3, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '2', 'iuc' => '2026/1']],
]);

it('rejects mode incompatible contingency evidence', function (int $mode, ?array $contingency, string $field, string $message): void {
    $payload = F::payload(['emission' => ['issueMode' => $mode, 'contingency' => $contingency]]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'offline without evidence' => [2, null, 'emission.contingency', 'The emission.contingency field is required.'],
    'offline without time'     => [2, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '4'],
        'emission.contingency.issueTime', 'The emission.contingency.issue time field is required.'],
    'offline with off reason' => [2, P::offlineContingency(['reasonTypeCode' => '2']), 'emission.contingency.reasonTypeCode', 'The selected emission.contingency.reason type code is invalid.'],
    'off without unique code' => [3, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '2'], 'emission.contingency.iuc', 'The emission.contingency.iuc field is required.'],
    'online with contingency' => [1, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '2'], 'emission.contingency', 'The emission.contingency field is prohibited.'],
]);
