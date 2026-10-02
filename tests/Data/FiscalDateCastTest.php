<?php

declare(strict_types=1);

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\PaymentsData;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class CustomFormatFiscalDateData extends Data
{
    public function __construct(
        #[WithCast(FiscalDateCast::class, 'd/m/Y')]
        public readonly CarbonImmutable $date,
    ) {}
}

it('preserves fiscal wall-clock fields across a host DST gap', function (): void {
    $originalTimezone = date_default_timezone_get();

    try {
        date_default_timezone_set('Europe/Luxembourg');
        CarbonImmutable::setTestNow('2026-03-29T12:00:00+02:00');

        $header = DocumentHeaderData::from(['issueDate' => '2026-03-29', 'issueTime' => '02:30:00', 'ledCode' => 1]);
        $event  = EventData::from([
            'emitterTaxId'           => ['value' => '100200300', 'countryCode' => 'CV'],
            'issueDateTime'          => '2026-03-29T02:30:00',
            'issueReasonDescription' => 'Document cancelled by emitter',
            'eventTypeCode'          => 'FDC',
            'iuds'                   => ['CV1261002100200300' . str_repeat('0', 27)],
        ]);

        expect($header->toArray())->toMatchArray(['issueDate' => '2026-03-29', 'issueTime' => '02:30:00'])
            ->and($header->issueDate->timezoneName)->toBe('Atlantic/Cape_Verde')
            ->and($header->issueTime->timezoneName)->toBe('Atlantic/Cape_Verde')
            ->and($header->issueTime->format('Y-m-d'))->toBe('1970-01-01')
            ->and($event->toArray()['issueDateTime'])->toBe('2026-03-29T02:30:00')
            ->and($event->issueDateTime->timezoneName)->toBe('Atlantic/Cape_Verde');
    } finally {
        date_default_timezone_set($originalTimezone);
    }
});

it('parses a validated custom fiscal date format', function (): void {
    $date = CustomFormatFiscalDateData::from(['date' => '29/03/2026'])->date;

    expect($date->format('Y-m-d'))->toBe('2026-03-29')
        ->and($date->timezoneName)->toBe('Atlantic/Cape_Verde');
});

it('reports a fiscal date cast failure at its full path', function (mixed $date): void {
    $payments    = array_fill(0, 4, ['paymentMeansCode' => '10', 'paymentDate' => '2026-10-02']);
    $payments[3] = ['paymentMeansCode' => '10', 'paymentDate' => $date];

    expect(fn (): PaymentsData => PaymentsData::from(['payments' => $payments]))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['payments.3.paymentDate' => ['The payments.3.payment date must use a valid fiscal date or time.']]);
        });
})->with([
    'overflowing day' => ['2026-02-30'],
    'trailing text'   => ['2026-10-02x'],
    'not a string'    => [20261002],
    'before earliest' => ['2020-12-31'],
]);

it('reports a top-level fiscal date cast failure at its own field', function (): void {
    expect(fn (): CustomFormatFiscalDateData => CustomFormatFiscalDateData::from(['date' => '31/02/2026']))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['date' => ['The date must use a valid fiscal date or time.']]);
        });
});
