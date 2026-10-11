<?php

declare(strict_types=1);

namespace Akira\Efatura\Readiness;

use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;

final readonly class ReadinessReport
{
    /**
     * @param list<ReadinessResult> $results
     */
    public function __construct(public array $results) {}

    public function ready(): bool
    {
        return $this->results !== [] && collect($this->results)->doesntContain(static fn (ReadinessResult $result): bool => $result->blocks());
    }

    /**
     * @return list<ReadinessResult>
     */
    public function of(ReadinessCheck $check): array
    {
        return array_values(array_filter($this->results, static fn (ReadinessResult $result): bool => $result->check === $check));
    }

    /**
     * @return list<ReadinessResult>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->results, static fn (ReadinessResult $result): bool => $result->status === ReadinessStatus::Warning));
    }
}
