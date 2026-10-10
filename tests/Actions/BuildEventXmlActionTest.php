<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildEventXmlAction;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Carbon\CarbonImmutable;

it('rejects an event id that names another event', function (array $overrides): void {
    $event = EventData::from(E::transmitted(['iuds' => [E::iud()]]));

    expect(fn (): string => resolve(BuildEventXmlAction::class)->handle($event, E::eventId($overrides), Environment::Test))
        ->toFailValidationOn('eventId', 'The eventId does not identify this event.');
})->with([
    'issue second'                   => [['issueDateTime' => '2026-10-02T12:00:01']],
    'repository'                     => [['repositoryCode' => Environment::Production->value]],
    'emitter instead of transmitter' => [['taxId' => '100200300']],
]);

it('rejects a malformed event id', function (): void {
    $event = EventData::from(E::transmitted(['iuds' => [E::iud()]]));

    expect(fn (): string => resolve(BuildEventXmlAction::class)->handle($event, 'CV3261002120000123456', Environment::Test))
        ->toFailValidationOn('eventId', 'The eventId must be an official event identifier.');
});

it('requires the transmission when the event xml is written', function (array $emission, string $field): void {
    $event = EventData::from(E::payload(['iuds' => [E::iud()], ...$emission]));

    expect(fn (): string => resolve(BuildEventXmlAction::class)->handle($event, E::eventId(), Environment::Test))
        ->toFailValidationOn($field, 'The ' . $field . ' is required to write the XML document.');
})->with([
    'emission'    => [[], 'emission'],
    'transmitter' => [['emission' => ['software' => DocumentPayloads::transmission()['software']]], 'emission.transmitterTaxId'],
]);

it('validates an event constructed directly before writing any xml', function (): void {
    $event = new EventData(
        eventType: EventType::FiscalDocumentCancellation,
        emitterTaxId: new TaxIdData('100200300', 'CV'),
        issueDateTime: new CarbonImmutable('2026-10-02T12:00:00', 'Atlantic/Cape_Verde'),
        issueReasonDescription: 'Document cancelled by emitter',
        iuds: [E::iud()],
        numberRange: new EventNumberRangeData(1, 'A', DocumentType::Invoice, 1, 3),
    );

    expect(fn (): string => resolve(BuildEventXmlAction::class)->handle($event, E::eventId(), Environment::Test))
        ->toFailValidationOn('numberRange', 'The number range field is prohibited.');
});
