<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Throwable;

final class ExchangeRateException extends EfaturaException
{
    /**
     * @param array<string, int|string> $context
     */
    private function __construct(public readonly ExchangeRateFailure $failure, array $context, ?Throwable $previous, bool $retryable)
    {
        parent::__construct($failure->value, $failure->value, context: $context, previous: $previous, retryable: $retryable || $failure->retryable());
    }

    /**
     * @param array<string, int|string> $context
     */
    public static function because(ExchangeRateFailure $failure, array $context = [], ?Throwable $previous = null, bool $retryable = false): self
    {
        return new self($failure, $context, $previous, $retryable);
    }
}
