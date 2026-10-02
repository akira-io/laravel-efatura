<?php

declare(strict_types=1);

namespace Akira\Efatura\Builders;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\EmitterConfig;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\EventType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Psr\Clock\ClockInterface;

final class EventBuilder
{
    private ?EventType $type = null;

    private TaxIdData|EmitterConfig|null $emitter;

    private CarbonImmutable $issuedAt;

    private ?string $reason = null;

    /** @var list<string> */
    private array $iuds = [];

    private ?EventNumberRangeData $numberRange = null;

    private ?EmissionContextData $emission = null;

    public function __construct(EfaturaConfig $config, ClockInterface $clock)
    {
        $this->emitter  = $config->emitter;
        $this->issuedAt = CarbonImmutable::instance($clock->now());
    }

    public function type(EventType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function emitter(TaxIdData $emitter): static
    {
        $this->emitter = $emitter;

        return $this;
    }

    public function issuedAt(CarbonInterface $dateTime): static
    {
        $this->issuedAt = $dateTime->toImmutable();

        return $this;
    }

    public function reason(string $description): static
    {
        $this->reason = $description;

        return $this;
    }

    public function iud(string $iud): static
    {
        $this->iuds[] = $iud;

        return $this;
    }

    public function numberRange(EventNumberRangeData $range): static
    {
        $this->numberRange = $range;

        return $this;
    }

    public function emission(EmissionContextData $emission): static
    {
        $this->emission = $emission;

        return $this;
    }

    public function validate(): EventData
    {
        return EventData::from([
            'eventTypeCode'          => $this->type,
            'emitterTaxId'           => $this->emitter instanceof EmitterConfig ? $this->emitter->taxIdPayload() : $this->emitter,
            'issueDateTime'          => $this->issuedAt,
            'issueReasonDescription' => $this->reason,
            'iuds'                   => $this->iuds,
            'numberRange'            => $this->numberRange,
            'emission'               => $this->emission,
        ]);
    }
}
