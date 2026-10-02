<?php

declare(strict_types=1);

use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Tests\Support\EventFixtures as E;

dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);

it('creates a cancellation event targeting document identifiers', function (string $method): void {
    $payload = E::payload(['iuds' => [E::iud()]]);

    $event = EventData::$method($payload);

    expect($event->eventType)->toBe(EventType::FiscalDocumentCancellation)
        ->and($event->iuds)->toBe([E::iud()]);
})->with('factories');

it('creates an unused number event targeting a number range', function (string $method): void {
    $payload = E::payload(['eventTypeCode' => 'UDN', 'numberRange' => E::numberRange(1, 3)]);

    $event = EventData::$method($payload);

    expect($event->numberRange?->documentNumberStart)->toBe(1)
        ->and($event->numberRange?->documentNumberEnd)->toBe(3);
})->with('factories');

it('rejects missing malformed conflicting and reversed event targets', function (array $changes, string $field, string $message, string $method): void {
    $payload = E::payload($changes);

    expect(fn (): EventData => EventData::$method($payload))->toFailValidationOn($field, $message);
})->with(fn (): array => [
    'cancellation without identifiers' => [[], 'iuds', 'The iuds field is required when event type code is FDC.'],
    'malformed identifier'             => [['iuds' => ['bad']], 'iuds.0', 'The iuds.0 field format is invalid.'],
    'reversed number range'            => [
        ['eventTypeCode' => 'UDN', 'numberRange' => E::numberRange(3, 1)],
        'numberRange.documentNumberEnd',
        'The number range.document number end field must be greater than or equal to 3.',
    ],
    'unused number event with identifiers' => [
        ['eventTypeCode' => 'UDN', 'numberRange' => E::numberRange(1, 2), 'iuds' => [E::iud()]],
        'iuds',
        'The iuds field is prohibited.',
    ],
    'cancellation with a number range' => [
        ['eventTypeCode' => 'FDC', 'iuds' => [E::iud()], 'numberRange' => E::numberRange(1, 2)],
        'numberRange',
        'The number range field is prohibited.',
    ],
])->with('factories');
