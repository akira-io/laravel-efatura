<?php

declare(strict_types=1);

use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\EventFixtures;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    $this->iud         = EventFixtures::iud();
    $this->numberRange = EventNumberRangeData::from(['ledCode' => 2, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 3]);
});

it('builds a cancellation event from the configured emitter and the clock', function (): void {
    config()->set('efatura.emitter.tax_id', '100200300');

    $event = Efatura::event()->type(EventType::FiscalDocumentCancellation)->reason('Document cancelled by emitter')->iud($this->iud)->build();

    expect($event->emitterTaxId->value)->toBe('100200300')
        ->and($event->issueDateTime->format('Y-m-d H:i:s'))->toBe('2026-10-02 12:00:00')
        ->and($event->iuds)->toBe([$this->iud]);
});

it('builds an unused number event with explicit emitter, context and issuance date', function (): void {
    $event = Efatura::efatura()->event()->type(EventType::UnusedDocumentNumber)->emitter(new TaxIdData('900800700', 'CV'))
        ->issuedAt(new CarbonImmutable('2026-10-01T10:00:00-01:00'))->reason('Unused invoice numbers in sequence')
        ->numberRange($this->numberRange)->emission(new EmissionContextData)->build();

    expect($event->numberRange->ledCode)->toBe(2)
        ->and($event->iuds)->toBe([])
        ->and($event->emitterTaxId->value)->toBe('900800700')
        ->and($event->issueDateTime->format('Y-m-d H:i:s'))->toBe('2026-10-01 10:00:00')
        ->and($event->emission->issueMode->value)->toBe(1);
});

it('keeps an event draft independent of other drafts', function (): void {
    config()->set('efatura.emitter.tax_id', '100200300');
    $draft = Efatura::event()->type(EventType::FiscalDocumentCancellation)->reason('Document cancelled by emitter')->iud($this->iud);

    Efatura::event()->emitter(new TaxIdData('900800700', 'CV'));

    expect($draft->build()->emitterTaxId->value)->toBe('100200300');
});

it('requires an emitter when the configuration has none', function (): void {
    config()->set('efatura.emitter');
    $draft = Efatura::event()->type(EventType::FiscalDocumentCancellation)->reason('Document cancelled by emitter')->iud($this->iud);

    expect(fn (): EventData => $draft->build())->toFailValidationOn('emitterTaxId', 'The emitter tax id field is required.');
});

it('rejects a number range on a cancellation event', function (): void {
    $draft = Efatura::event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', 'CV'))
        ->reason('Document cancelled by emitter')->iud($this->iud)->numberRange($this->numberRange);

    expect(fn (): EventData => $draft->build())->toFailValidationOn('numberRange', 'The number range field is prohibited.');
});
