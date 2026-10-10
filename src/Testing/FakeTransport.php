<?php

declare(strict_types=1);

namespace Akira\Efatura\Testing;

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transport\DocumentLookup;
use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

final class FakeTransport implements Transport
{
    private const int STATUS = 200;

    /** @var list<PackagedArchive> */
    private array $submitted = [];

    /** @var list<string> */
    private array $lookedUp = [];

    /** @var array<string, list<EntryResult|TransportFailure>> */
    private array $outcomes = [];

    /** @var array<string, string> */
    private array $documents = [];

    public function __construct(private readonly ClockInterface $clock) {}

    public function accept(string $identifier, ?string $authorizationCode = null): self
    {
        return $this->queue($identifier, $this->accepted($identifier, $authorizationCode));
    }

    public function reject(string $identifier, RemoteMessage ...$messages): self
    {
        return $this->queue($identifier, new EntryResult($identifier . '.xml', $identifier, false, null, null, array_values($messages)));
    }

    public function fail(string $identifier, TransportFailure $failure): self
    {
        return $this->queue($identifier, $failure);
    }

    public function holdDocument(string $iud, string $xml): self
    {
        $this->documents[$iud] = $xml;

        return $this;
    }

    public function submit(PackagedArchive $archive): SubmissionResult
    {
        $this->submitted[] = $archive;
        $entries           = [];
        foreach (array_map($this->next(...), $archive->identifiers()) as $outcome) {
            if ($outcome instanceof TransportFailure) {
                throw TransportException::because($outcome, $archive);
            }

            $entries[] = $outcome;
        }

        return new SubmissionResult($archive->kind, self::STATUS, $entries, $this->now());
    }

    public function lookupDocument(string $iud): DocumentLookup
    {
        $this->lookedUp[] = $iud;
        $xml              = $this->documents[$iud] ?? null;

        return new DocumentLookup($iud, $xml !== null, $xml);
    }

    /**
     * @return list<PackagedArchive>
     */
    public function submitted(): array
    {
        return $this->submitted;
    }

    /**
     * @return list<string>
     */
    public function lookedUp(): array
    {
        return $this->lookedUp;
    }

    private function queue(string $identifier, EntryResult|TransportFailure $outcome): self
    {
        $this->outcomes[$identifier][] = $outcome;

        return $this;
    }

    private function next(string $identifier): EntryResult|TransportFailure
    {
        $queued                      = $this->outcomes[$identifier] ?? [];
        $outcome                     = array_shift($queued) ?? $this->accepted($identifier, null);
        $this->outcomes[$identifier] = $queued;

        return $outcome;
    }

    private function accepted(string $identifier, ?string $authorizationCode): EntryResult
    {
        $signedAt = Fiscal::local($this->now())->format('Y-m-d H:i:s');

        return new EntryResult($identifier . '.xml', $identifier, true, $authorizationCode ?? 'FAKE-' . $identifier, $signedAt, []);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->clock->now());
    }
}
