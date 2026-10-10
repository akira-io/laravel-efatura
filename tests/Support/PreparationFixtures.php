<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Sequence\InMemorySequenceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

final class PreparationFixtures
{
    public const string TRANSMITTER = '123456789';

    /**
     * @param array<string, string|null> $config
     */
    public static function configure(string $format = 'pem', ?TestCertificate $certificate = null, array $config = []): InMemorySequenceStore
    {
        CertificateFixtures::store($format, $certificate);
        config()->set([
            'efatura.environment'        => 'test',
            'efatura.transmitter.tax_id' => self::TRANSMITTER,
            'efatura.software.code'      => 'APP',
            'efatura.software.name'      => 'Fiscal App',
            'efatura.software.version'   => '1.0',
            ...$config,
        ]);
        app()->forgetInstance(EfaturaConfig::class);
        CarbonImmutable::setTestNow(DocumentXmlGraphs::NOW);
        $store = new InMemorySequenceStore;
        app()->instance(SequenceStore::class, $store);

        return $store;
    }

    public static function expired(): TestCertificate
    {
        return CertificateFixtures::issue(validTo: '2026-10-01T00:00:00Z');
    }

    /**
     * @return list<mixed>
     */
    public static function packageArguments(Throwable $exception): array
    {
        return collect([$exception, $exception->getPrevious()])
            ->filter()
            ->flatMap(fn (Throwable $thrown): array => $thrown->getTrace())
            ->filter(fn (array $frame): bool => Str::startsWith($frame['class'] ?? '', 'Akira\Efatura\\'))
            ->reject(fn (array $frame): bool => Str::startsWith($frame['class'] ?? '', 'Akira\Efatura\Tests\\'))
            ->flatMap(fn (array $frame): array => $frame['args'] ?? [])
            ->map(fn (mixed $argument): mixed => $argument instanceof Throwable ? $argument::class : $argument)
            ->values()
            ->all();
    }

    public static function invoice(array $header = ['serie' => 'A'], array $overrides = ['emission' => null]): ElectronicInvoiceData
    {
        return SequenceFixtures::invoice($header, $overrides);
    }

    public static function event(array $overrides = []): EventData
    {
        return EventData::from(EventFixtures::payload(['iuds' => [EventFixtures::iud()], ...$overrides]));
    }

    public static function unusedNumbers(): EventData
    {
        return EventData::from(EventFixtures::payload(['eventTypeCode' => 'UDN', 'numberRange' => EventFixtures::numberRange(1, 3)]));
    }
}
