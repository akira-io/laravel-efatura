<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Enums\ReceiptType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('hydrates the header series from serie and writes it back as serie', function (): void {
    $header = DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => 'A']);

    expect($header->series)->toBe('A')
        ->and($header->toArray())->toHaveKey('serie', 'A')
        ->and($header->toArray())->not->toHaveKey('series');
});

it('hydrates the number range series from serie and writes it back as serie', function (): void {
    $range = EventNumberRangeData::from(['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 2]);

    expect($range->series)->toBe('A')
        ->and($range->toArray())->toHaveKey('serie', 'A')
        ->and($range->toArray())->not->toHaveKey('series');
});

it('reports an invalid series under serie', function (): void {
    expect(fn (): DocumentHeaderData => DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:00:00', 'ledCode' => 1, 'serie' => 'bad space']))
        ->toThrow(fn (ValidationException $exception): mixed => expect($exception->errors())->toHaveKey('serie')->not->toHaveKey('series'));
});

it('hydrates a nested enum property from its XML code name and writes it back under that name', function (): void {
    $tax = TaxData::from(['taxTypeCode' => 'IVA', 'taxPercentage' => '15']);

    expect($tax->taxType)->toBe(TaxType::ValueAddedTax)
        ->and($tax->toArray())->toHaveKey('taxTypeCode', 'IVA')
        ->and($tax->toArray())->not->toHaveKey('taxType');
});

it('hydrates a document enum property from its XML code name and writes it back under that name', function (): void {
    $receipt = ReceiptData::from(F::receiptPayload('1'));

    expect($receipt->receiptType)->toBe(ReceiptType::Commercial)
        ->and($receipt->toArray())->toHaveKey('receiptTypeCode', '1')
        ->and($receipt->toArray())->not->toHaveKey('receiptType');
});

it('reports an invalid document enum value under its XML code name', function (): void {
    expect(fn (): ReceiptData => ReceiptData::from(F::receiptPayload('9')))
        ->toThrow(fn (ValidationException $exception): mixed => expect($exception->errors())->toHaveKey('receiptTypeCode')->not->toHaveKey('receiptType'));
});
