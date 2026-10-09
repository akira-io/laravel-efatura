<?php

declare(strict_types=1);

use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\FiscalData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Tests\Fixtures\CustomFormatFiscalDateData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->originalTimezone = date_default_timezone_get();
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
});

afterEach(function (): void {
    date_default_timezone_set($this->originalTimezone);
});

dataset('host instants', [
    'immutable' => [static fn (): CarbonInterface => CarbonImmutable::parse('2026-10-03 00:30', 'UTC')],
    'mutable'   => [static fn (): CarbonInterface => Date::parse('2026-10-03 00:30', 'UTC')],
]);

it('stores issuance instants in Cape Verde time and rebuilds them unchanged', function (Closure $moment, Closure $create, array $properties): void {
    $data    = $create($moment());
    $rebuilt = $data::from($data->toArray());

    foreach ($properties as $property => $expected) {
        expect($data->{$property}->timezoneName)->toBe('Atlantic/Cape_Verde')
            ->and($data->{$property}->format('Y-m-d H:i:s'))->toBe($expected)
            ->and($rebuilt->{$property}->equalTo($data->{$property}))->toBeTrue();
    }

    expect($rebuilt->toArray())->toBe($data->toArray());
})->with('host instants')->with([
    'header' => [
        static fn (CarbonInterface $moment): FiscalData => DocumentHeaderData::from(['issueDate' => $moment, 'issueTime' => $moment, 'ledCode' => 1]),
        ['issueDate' => '2026-10-02 00:00:00', 'issueTime' => '1970-01-01 23:30:00'],
    ],
    'contingency' => [
        static fn (CarbonInterface $moment): FiscalData => ContingencyData::from(['issueDate' => $moment, 'issueTime' => $moment, 'reasonTypeCode' => ContingencyReason::Other,
            'ledCode'                                                                         => 1, 'reasonDescription' => 'Network outage at the store']),
        ['issueDate' => '2026-10-02 00:00:00', 'issueTime' => '1970-01-01 23:30:00'],
    ],
    'event' => [
        static fn (CarbonInterface $moment): FiscalData => EventData::from(E::payload(['issueDateTime' => $moment, 'iuds' => [E::iud()]])),
        ['issueDateTime' => '2026-10-02 23:30:00'],
    ],
]);

it('keeps the given calendar date of an immutable host date', function (): void {
    $invoice = ElectronicInvoiceData::from(F::payload(['dueDate' => CarbonImmutable::parse('2026-10-20 00:30', 'UTC')]));

    expect($invoice->dueDate?->format('Y-m-d'))->toBe('2026-10-20')
        ->and($invoice->dueDate?->timezoneName)->toBe('Atlantic/Cape_Verde')
        ->and(ElectronicInvoiceData::from($invoice->toArray())->dueDate?->equalTo($invoice->dueDate))->toBeTrue();
});

it('formats a mutable carbon given to the fiscal date cast outside fiscal data', function (): void {
    $date = CustomFormatFiscalDateData::from(['date' => Date::parse('2026-03-29 23:30', 'UTC')])->date;

    expect($date->format('Y-m-d'))->toBe('2026-03-29')
        ->and($date->timezoneName)->toBe('Atlantic/Cape_Verde');
});
