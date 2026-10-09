<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildEventXmlAction;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Akira\Efatura\Tests\Support\IdentifierFixtures;
use Carbon\CarbonImmutable;

dataset('events', fn (): array => [
    'FDC' => ['FDC', E::payload(['iuds' => [E::iud(), IdentifierFixtures::NODE_IUD]])],
    'UDN' => ['UDN', E::payload(['eventTypeCode' => 'UDN', 'numberRange' => [...E::numberRange(1, 10), 'year' => 2026]])],
]);

it('writes each event byte for byte as its reviewed fixture', function (string $code, array $payload): void {
    $event = EventData::from([...$payload, 'emission' => DocumentPayloads::transmission()]);

    expect(resolve(BuildEventXmlAction::class)->handle($event, E::eventId(), Environment::Test))
        ->toBe((string) file_get_contents(__DIR__ . '/../Fixtures/xml/events/' . $code . '.xml'));
})->with('events');

it('writes the emitter tax id of the cancelled document, not the transmitter', function (): void {
    $event = EventData::from(E::transmitted(['iuds' => [E::iud()]]));

    expect(resolve(BuildEventXmlAction::class)->handle($event, E::eventId(), Environment::Test))
        ->toContain('<EmitterTaxId CountryCode="CV">100200300</EmitterTaxId>')
        ->toContain('<TransmitterTaxId CountryCode="CV">123456789</TransmitterTaxId>');
});

it('writes the year of an unused number range only when it is given', function (): void {
    $event = EventData::from(E::transmitted(['eventTypeCode' => 'UDN', 'numberRange' => E::numberRange(4, 9)]));

    expect(resolve(BuildEventXmlAction::class)->handle($event, E::eventId(), Environment::Test))
        ->not->toContain('<Year>')
        ->toContain('<IssueReasonDescription>Document cancelled by emitter</IssueReasonDescription><LedCode>1</LedCode><Serie>A</Serie>'
            . '<DocumentTypeCode>1</DocumentTypeCode><DocumentNumberStart>4</DocumentNumberStart><DocumentNumberEnd>9</DocumentNumberEnd><Transmission>');
});

it('writes the issue date time in cabo verde time', function (): void {
    $event = EventData::from(E::transmitted(['iuds' => [E::iud()], 'issueDateTime' => new CarbonImmutable('2026-10-03T00:30:00Z')]));

    expect(resolve(BuildEventXmlAction::class)->handle($event, 'CV3261002233000123456789', Environment::Test))
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<Event xmlns="urn:cv:efatura:xsd:v1.0" Id="CV3261002233000123456789" Version="1.0" EventTypeCode="FDC">')
        ->toContain('<IssueDateTime>2026-10-02T23:30:00</IssueDateTime>');
});
