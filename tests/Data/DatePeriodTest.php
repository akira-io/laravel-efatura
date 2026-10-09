<?php

declare(strict_types=1);

use Akira\Efatura\Data\DatePeriodData;
use Carbon\CarbonImmutable;

dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);
dataset('same calendar day across timezones', fn (): array => [[[
    'startDate' => CarbonImmutable::parse('2026-10-02 23:00:00', 'Pacific/Honolulu'),
    'endDate'   => CarbonImmutable::parse('2026-10-02 00:00:00', 'Pacific/Kiritimati'),
]]]);

it('accepts a same day period through the constructor regardless of hidden time and timezone', function (array $payload): void {
    expect(new DatePeriodData(...$payload)->toArray()['endDate'])->toBe('2026-10-02');
})->with('same calendar day across timezones');

it('accepts a same day period through the factories regardless of hidden time and timezone', function (array $payload, string $method): void {
    expect(DatePeriodData::$method($payload)->toArray()['endDate'])->toBe('2026-10-02');
})->with('same calendar day across timezones')->with('factories');

it('rejects a period ending on an earlier calendar date', function (string $method): void {
    $payload = ['startDate' => '2026-10-03', 'endDate' => '2026-10-02'];

    expect(fn (): DatePeriodData => DatePeriodData::$method($payload))
        ->toFailValidationOn('endDate', 'The end date field must be a date after or equal to startDate.');
})->with('factories');
