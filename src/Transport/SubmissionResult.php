<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SubmissionOutcome;
use Akira\Efatura\Exceptions\DefinitionException;
use Carbon\CarbonImmutable;

final readonly class SubmissionResult
{
    /**
     * @param list<EntryResult> $entries
     */
    public function __construct(
        public PackageKind $kind,
        public int $status,
        public array $entries,
        public CarbonImmutable $receivedAt,
    ) {}

    public function outcome(): SubmissionOutcome
    {
        $succeeded = collect($this->entries)->filter(static fn (EntryResult $entry): bool => $entry->succeeded)->count();

        if ($succeeded === 0) {
            return SubmissionOutcome::Rejected;
        }

        return $succeeded === \count($this->entries) ? SubmissionOutcome::Accepted : SubmissionOutcome::Partial;
    }

    public function entry(string $identifier): EntryResult
    {
        return collect($this->entries)->first(static fn (EntryResult $entry): bool => $entry->identifier === $identifier)
            ?? throw DefinitionException::submissionEntry($identifier);
    }
}
