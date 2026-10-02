<?php

declare(strict_types=1);

namespace Akira\Efatura\Builders;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Clock;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;

final class EventBuilder
{
    /** @var array<string, mixed> */
    private array $draft;

    /** @var list<string> */
    private array $iuds = [];

    public function __construct(EfaturaConfig $config, Clock $clock)
    {
        $this->draft = ['emitterTaxId' => ConfiguredEmitter::taxId($config->emitter), 'issueDateTime' => $clock->now()->format(Fiscal::DATE_TIME_FORMAT)];
    }

    public function type(EventType $type): self
    {
        $this->draft['eventTypeCode'] = $type->value;

        return $this;
    }

    public function emitter(TaxIdData $emitter): self
    {
        $this->draft['emitterTaxId'] = $emitter->toArray();

        return $this;
    }

    public function issuedAt(CarbonImmutable $dateTime): self
    {
        $this->draft['issueDateTime'] = $dateTime->format(Fiscal::DATE_TIME_FORMAT);

        return $this;
    }

    public function reason(string $description): self
    {
        $this->draft['issueReasonDescription'] = $description;

        return $this;
    }

    public function iud(string $iud): self
    {
        $this->iuds[]        = $iud;
        $this->draft['iuds'] = $this->iuds;

        return $this;
    }

    public function numberRange(EventNumberRangeData $range): self
    {
        $this->draft['numberRange'] = $range->toArray();

        return $this;
    }

    public function emission(EmissionContextData $emission): self
    {
        $this->draft['emission'] = $emission->toArray();

        return $this;
    }

    public function validate(): EventData
    {
        return EventData::validateAndCreate($this->draft);
    }
}
