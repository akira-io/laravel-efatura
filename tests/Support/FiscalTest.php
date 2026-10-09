<?php

declare(strict_types=1);

use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;

it('anchors fiscal time to Cabo Verde without daylight saving', function (): void {
    $winter = CarbonImmutable::parse('2026-01-15 12:00:00', Fiscal::TIMEZONE);
    $summer = CarbonImmutable::parse('2026-07-15 12:00:00', Fiscal::TIMEZONE);

    expect($winter->getOffsetString())->toBe('-01:00')
        ->and($summer->getOffsetString())->toBe('-01:00');
});

it('parses fiscal date, time and date time formats in Cabo Verde', function (string $format, string $value): void {
    $parsed = Fiscal::parse($value, $format);

    expect($parsed?->format($format))->toBe($value)
        ->and($parsed?->getTimezone()->getName())->toBe(Fiscal::TIMEZONE);
})->with([
    'date'      => [Fiscal::DATE_FORMAT, '2026-10-02'],
    'time'      => [Fiscal::TIME_FORMAT, '23:30:00'],
    'date time' => [Fiscal::DATE_TIME_FORMAT, '2026-10-02T23:30:00'],
]);

it('rejects dated values before the earliest accepted fiscal date', function (string $format, string $value): void {
    expect(Fiscal::parse($value, $format))->toBeNull();
})->with([
    'date'      => [Fiscal::DATE_FORMAT, '2020-12-31'],
    'date time' => [Fiscal::DATE_TIME_FORMAT, '2020-12-31T23:59:59'],
]);

it('accepts the first instant of the earliest accepted fiscal date', function (string $format, string $value): void {
    expect(Fiscal::parse($value, $format)?->format($format))->toBe($value);
})->with([
    'date'      => [Fiscal::DATE_FORMAT, Fiscal::EARLIEST_DATE],
    'date time' => [Fiscal::DATE_TIME_FORMAT, Fiscal::EARLIEST_DATE . 'T00:00:00'],
]);

it('rejects values that do not round trip through the fiscal format', function (string $value): void {
    expect(Fiscal::parse($value, Fiscal::DATE_FORMAT))->toBeNull();
})->with([
    'overflowing day' => ['2026-02-30'],
    'not a date'      => ['tomorrow'],
]);
