<?php

declare(strict_types=1);

use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Facades\Efatura;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});
afterEach(fn () => CarbonImmutable::setTestNow());

it('builds isolated cancellation and unused number events with explicit context and date overrides', function (): void {
    config()->set('efatura.emitter.tax_id', '100200300');
    $id    = 'CV1261002100200300' . str_repeat('0', 27);
    $draft = Efatura::event()->type(EventType::FiscalDocumentCancellation)->reason('Document cancelled by emitter')->iud($id);
    $event = $draft->build();
    expect($event->emitterTaxId->value)->toBe('100200300')->and($event->issueDateTime->format('H:i:s'))->toBe('12:00:00')->and($event->iuds)->toBe([$id]);
    $other = Efatura::efatura()->event()->type(EventType::UnusedDocumentNumber)->emitter(new TaxIdData('900800700', 'CV'))
        ->issuedAt(new CarbonImmutable('2026-10-01T10:00:00-01:00'))->reason('Unused invoice numbers in sequence')
        ->numberRange(EventNumberRangeData::from(['ledCode' => 2, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 3]))
        ->emission(new EmissionContextData)->build();
    expect($other->numberRange->ledCode)->toBe(2)->and($other->iuds)->toBe([])->and($other->emitterTaxId->value)->toBe('900800700')
        ->and($other->issueDateTime->format('Y-m-d'))->toBe('2026-10-01')->and($other->emission->issueMode->value)->toBe(1)
        ->and($draft->build()->emitterTaxId->value)->toBe('100200300');
});

it('rejects missing emitters and conflicting event targets at validation', function (): void {
    config()->set('efatura.emitter');
    $id = 'CV1261002100200300' . str_repeat('0', 27);

    try {
        Efatura::event()->type(EventType::FiscalDocumentCancellation)->reason('Document cancelled by emitter')->iud($id)->build();
        test()->fail('Expected a missing emitter validation error');
    } catch (ValidationException $validationException) {
        expect(array_keys($validationException->errors()))->toContain('emitterTaxId');
    }

    $draft = Efatura::event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', 'CV'))
        ->reason('Document cancelled by emitter')->numberRange(EventNumberRangeData::from(['ledCode' => 2, 'serie' => 'A', 'documentTypeCode' => 'FTE', 'documentNumberStart' => 1, 'documentNumberEnd' => 3]));
    expect(fn (): EventData => $draft->build())->toThrow(ValidationException::class);
});
