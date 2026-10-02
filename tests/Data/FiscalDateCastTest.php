<?php

declare(strict_types=1);

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventData;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\LaravelDataServiceProvider;

final class CustomFormatFiscalDateData extends Data
{
    public function __construct(
        #[WithCast(FiscalDateCast::class, 'd/m/Y')]
        public readonly CarbonImmutable $date,
    ) {}
}

it('preserves fiscal wall-clock fields across a host DST gap', function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
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
        CarbonImmutable::setTestNow();
        date_default_timezone_set($originalTimezone);
    }
});

it('parses a validated custom fiscal date format', function (): void {
    $this->app->register(LaravelDataServiceProvider::class);

    $date = CustomFormatFiscalDateData::from(['date' => '29/03/2026'])->date;

    expect($date->format('Y-m-d'))->toBe('2026-03-29')
        ->and($date->timezoneName)->toBe('Atlantic/Cape_Verde');
});
