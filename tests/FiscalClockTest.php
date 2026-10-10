<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\EventFixtures;
use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

it('binds a shared PSR clock in the fiscal timezone', function (): void {
    $clock = resolve(ClockInterface::class);

    expect($clock)->toBe(resolve(ClockInterface::class))
        ->and($clock->now()->getTimezone()->getName())->toBe(Fiscal::TIMEZONE);
});

it('reports the frozen instant as Cabo Verde wall clock time', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 00:30:00', 'UTC'));

    expect(resolve(ClockInterface::class)->now()->format(Fiscal::DATE_TIME_FORMAT))->toBe('2026-10-02T23:30:00');
});

it('stamps builders from the injected clock, also on scoped managers', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 00:30:00', 'UTC'));
    $scoped = resolve(EfaturaManager::class)->withConfig(resolve(LoadEfaturaConfig::class)());

    $event = $scoped->event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', Fiscal::COUNTRY))
        ->reason('Document cancelled by emitter')->iud(EventFixtures::iud())->build();

    expect($event->issueDateTime->format(Fiscal::DATE_TIME_FORMAT))->toBe('2026-10-02T23:30:00');
});
