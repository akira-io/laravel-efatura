<?php

declare(strict_types=1);

use Akira\Efatura\Enums\EventIdSegment;
use Akira\Efatura\Enums\IudSegment;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;

it('lays the IUD segments end to end after the country code', function (IudSegment $segment, int $offset, int $length, string $value): void {
    expect($segment->offset())->toBe($offset)
        ->and($segment->length())->toBe($length)
        ->and($segment->of(I::NODE_IUD))->toBe($value);
})->with([
    'repository'      => [IudSegment::Repository, 2, 1, '3'],
    'issue date'      => [IudSegment::IssueDate, 3, 6, '260208'],
    'emitter tax id'  => [IudSegment::EmitterTaxId, 9, 9, '100200300'],
    'LED'             => [IudSegment::LedCode, 18, 5, '00123'],
    'document type'   => [IudSegment::DocumentType, 23, 2, '01'],
    'document number' => [IudSegment::DocumentNumber, 25, 9, '000000001'],
    'random code'     => [IudSegment::RandomCode, 34, 10, '1234567890'],
]);

it('leaves only the check digit after the last IUD segment', function (): void {
    expect(IudSegment::RandomCode->offset() + IudSegment::RandomCode->length())->toBe(strlen(I::NODE_IUD) - 1);
});

it('lays the event id segments end to end after the country code', function (EventIdSegment $segment, int $offset, int $length, string $value): void {
    expect($segment->offset())->toBe($offset)
        ->and($segment->length())->toBe($length)
        ->and($segment->of(I::OFFICIAL_EVENT_ID))->toBe($value);
})->with([
    'repository'      => [EventIdSegment::Repository, 2, 1, '1'],
    'issue date time' => [EventIdSegment::IssueDateTime, 3, 12, '210805181011'],
    'tax id'          => [EventIdSegment::TaxId, 15, 9, '123456789'],
]);

it('pads a value to the width of its segment', function (): void {
    expect(IudSegment::LedCode->padded(7))->toBe('00007')
        ->and(IudSegment::DocumentNumber->padded(999999999))->toBe('999999999')
        ->and(EventIdSegment::Repository->padded(3))->toBe('3');
});
