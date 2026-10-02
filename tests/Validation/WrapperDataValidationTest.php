<?php

declare(strict_types=1);
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

beforeEach(fn () => CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00'));
afterEach(fn () => CarbonImmutable::setTestNow());
it('validates document graphs assembled from data objects', function (): void {
    $payload = F::payload();
    $graph   = ['header' => DocumentHeaderData::from($payload['header']), 'emitter' => PartyData::from($payload['emitter']), 'receiver' => PartyData::from($payload['receiver']), 'lines' => [], 'totals' => F::totals()];

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($graph))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toHaveKey('lines');
        })
        ->and(ElectronicInvoiceData::from([...$graph, 'lines' => [F::line()]])->lines)->toHaveCount(1);
});
it('enforces rent receipt requirements without invoice sections', function (): void {
    $payload = F::payload(['receiptTypeCode' => '4', 'references' => F::references(), 'payments' => F::payments()]);
    unset($payload['lines'], $payload['totals']);
    expect(fn (): ReceiptData => ReceiptData::from($payload))->toThrow(ValidationException::class);
    $payload['rentReceipt'] = ['assetId' => 'HOUSE', 'rentPurposeTypeCode' => '2', 'contractTypeCode' => '1', 'rentTypeCode' => '1', 'referencePeriod' => '2026-10', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']];
    expect(ReceiptData::from($payload)->rentReceipt->assetId)->toBe('HOUSE');
    $payload['lines'] = [];
    expect(fn (): ReceiptData => ReceiptData::from($payload))->toThrow(ValidationException::class);
});
it('rejects transport totals and global receivers', function (): void {
    $payload = F::payload(['transportDocumentTypeCode' => '2', 'receiverTypeCode' => '3', 'transportServiceProvider' => ['reference' => 'EP'], 'transportRoute' => F::route()]);
    unset($payload['totals']);
    expect(fn (): TransportDocumentData => TransportDocumentData::from($payload))->toThrow(ValidationException::class);
    $payload['receiver'] = null;
    $payload['totals']   = F::totalsPayload();
    expect(fn (): TransportDocumentData => TransportDocumentData::from($payload))->toThrow(ValidationException::class);
});
it('preserves self billing authorization', function (): void {
    $payload                          = F::payload();
    $payload['header']['selfBilling'] = ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234'];
    expect(ElectronicInvoiceData::from($payload)->header->selfBilling->authorizationCode)->toBe('1234');
});
it('enforces inclusive online date windows in Cabo Verde time', function (string $date, string $time, bool $valid): void {
    $payload = F::payload(['header' => ['issueDate' => $date, 'issueTime' => $time, 'ledCode' => 1]]);
    if ($valid) {
        expect(ElectronicInvoiceData::from($payload)->header->issueDate->format('Y-m-d'))->toBe($date);
    } else {
        expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toThrow(ValidationException::class);
    }
})->with([['2026-10-02', '13:00:00', true], ['2026-10-02', '13:00:01', false], ['2026-10-01', '12:00:00', true], ['2026-10-01', '11:59:59', false]]);
it('requires mode compatible contingency evidence', function (int $mode, ?array $contingency, bool $valid): void {
    $payload = F::payload(['emission' => ['issueMode' => $mode, 'contingency' => $contingency]]);
    if ($valid) {
        expect(ElectronicInvoiceData::from($payload)->emission->issueMode)->toBe(EmissionMode::from($mode));
    } else {
        expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toThrow(ValidationException::class);
    }
})->with([
    [2, null, false],
    [2, ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'reasonTypeCode' => '4'], true],
    [2, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '4'], false],
    [2, ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'reasonTypeCode' => '2'], false],
    [3, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '2', 'iuc' => '2026/1'], true],
    [3, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '2'], false],
    [1, ['issueDate' => '2026-10-02', 'ledCode' => 1, 'reasonTypeCode' => '2'], false],
]);
