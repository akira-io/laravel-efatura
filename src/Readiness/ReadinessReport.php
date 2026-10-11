<?php

declare(strict_types=1);

namespace Akira\Efatura\Readiness;

use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;

/**
 * @phpstan-type PresentedCheck array{check: string, status: string, code: string, field: ?string, required: bool, context: array<string, scalar|null>}
 */
final readonly class ReadinessReport
{
    private const array PRESENTED_CONTEXT = ['days_left' => true, 'driver' => true, 'environment' => true, 'skew_seconds' => true, 'status' => true];

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

    /**
     * @return array{ready: bool, checks: list<PresentedCheck>}
     */
    public function toArray(): array
    {
        return [
            'ready'  => $this->ready(),
            'checks' => array_map(static fn (ReadinessResult $result): array => [
                'check'    => $result->check->value,
                'status'   => $result->status->value,
                'code'     => $result->code,
                'field'    => $result->field,
                'required' => $result->required,
                'context'  => array_intersect_key($result->context, self::PRESENTED_CONTEXT),
            ], $this->results),
        ];
    }
}
