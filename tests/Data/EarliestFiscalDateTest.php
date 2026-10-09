<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Fixtures\CustomFormatFiscalDateData;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Carbon\CarbonImmutable;

it('rejects an event instant that falls before the earliest date in Cabo Verde', function (mixed $issueDateTime): void {
    $payload = E::payload(['issueDateTime' => $issueDateTime, 'iuds' => [E::iud()]]);

    expect(fn (): EventData => EventData::from($payload))
        ->toFailValidationOn('issueDateTime', 'The issue date time must use a valid fiscal date or time.')
        ->and(fn (): array => EventData::validate($payload))
        ->toFailValidationOn('issueDateTime', 'The issue date time must use a valid fiscal date or time.');
})->with([
    'Cabo Verde text'    => ['2020-12-31T23:30:00'],
    'UTC Carbon instant' => [CarbonImmutable::parse('2021-01-01 00:30:00', 'UTC')],
]);

it('round-trips the first instant of the earliest date in Cabo Verde', function (): void {
    $event = EventData::from(E::payload(['issueDateTime' => CarbonImmutable::parse('2021-01-01 01:00:00', 'UTC'), 'iuds' => [E::iud()]]));

    expect($event->toArray()['issueDateTime'])->toBe('2021-01-01T00:00:00')
        ->and(EventData::from($event->toArray())->toArray())->toBe($event->toArray());
});

it('rejects a header instant whose Cabo Verde date precedes the earliest date', function (): void {
    $moment = CarbonImmutable::parse('2021-01-01 00:30:00', 'UTC');

    expect(fn (): DocumentHeaderData => DocumentHeaderData::from(['issueDate' => $moment, 'issueTime' => $moment, 'ledCode' => 1]))
        ->toFailValidationOn('issueDate', 'The issue date must use a valid fiscal date or time.');
});

it('applies the earliest date to custom dated formats and not to times', function (): void {
    $rule   = new FiscalDate(Fiscal::TIME_FORMAT);
    $failed = false;
    $rule->validate('time', '00:00:00', function () use (&$failed): void {
        $failed = true;
    });

    expect(fn (): CustomFormatFiscalDateData => CustomFormatFiscalDateData::from(['date' => '31/12/2020']))
        ->toFailValidationOn('date', 'The date must use a valid fiscal date or time.')
        ->and(Fiscal::parse('00:00:00', Fiscal::TIME_FORMAT)?->format(Fiscal::TIME_FORMAT))->toBe('00:00:00')
        ->and(Fiscal::parse('2021-01-01', Fiscal::DATE_FORMAT)?->format(Fiscal::DATE_FORMAT))->toBe('2021-01-01')
        ->and($failed)->toBeFalse();
});
