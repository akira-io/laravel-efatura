<?php

declare(strict_types=1);
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\EventType;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('validates cancellation and unused number event payload choices', function (): void {
    $common = ['emitterTaxId' => ['value' => '100200300', 'countryCode' => 'CV'], 'issueDateTime' => '2026-10-02T12:00:00', 'issueReasonDescription' => 'Document cancelled by emitter'];
    $id     = 'CV1261002100200300' . str_repeat('0', 27);
    expect(EventData::from([...$common, 'eventTypeCode' => 'FDC', 'iuds' => [$id]])->eventTypeCode)->toBe(EventType::FiscalDocumentCancellation);
    $event = EventData::validateAndCreate([...$common, 'eventTypeCode' => 'UDN', 'numberRange' => ['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 3]]);
    expect($event->numberRange->documentNumberEnd)->toBe(3);
});
it('rejects missing conflicting and reversed event targets', function (array $changes): void {
    $payload = ['emitterTaxId' => ['value' => '100200300', 'countryCode' => 'CV'], 'issueDateTime' => '2026-10-02T12:00:00', 'issueReasonDescription' => 'Document cancelled by emitter', 'eventTypeCode' => 'FDC'];
    foreach (['from', 'validateAndCreate'] as $factory) {
        expect(fn (): EventData => EventData::$factory(array_replace($payload, $changes)))->toThrow(ValidationException::class);
    }
})->with([[[]], [['iuds' => ['bad']]],
    [['eventTypeCode' => 'UDN', 'numberRange' => ['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 3, 'documentNumberEnd' => 1]]],
    [['eventTypeCode' => 'UDN', 'iuds' => ['CV1261002100200300' . str_repeat('0', 27)]]],
    [['eventTypeCode' => 'FDC', 'numberRange' => ['ledCode' => 1, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 2]]],
]);
