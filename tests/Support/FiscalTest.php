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

it('round trips fiscal date, time and date time formats', function (string $format, string $value): void {
    expect(CarbonImmutable::createFromFormat('!' . $format, $value, Fiscal::TIMEZONE)?->format($format))->toBe($value);
})->with([
    'date'      => [Fiscal::DATE_FORMAT, '2026-10-02'],
    'time'      => [Fiscal::TIME_FORMAT, '23:30:00'],
    'date time' => [Fiscal::DATE_TIME_FORMAT, '2026-10-02T23:30:00'],
]);

it('rejects fiscal dates before the earliest accepted date', function (): void {
    expect(validator(['date' => '2020-12-31'], ['date' => ['after_or_equal:' . Fiscal::EARLIEST_DATE]])->fails())->toBeTrue()
        ->and(validator(['date' => Fiscal::EARLIEST_DATE], ['date' => ['after_or_equal:' . Fiscal::EARLIEST_DATE]])->passes())->toBeTrue();
});
