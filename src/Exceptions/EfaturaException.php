<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use RuntimeException;
use Throwable;

abstract class EfaturaException extends RuntimeException
{
    /**
     * @param array<string, bool|int|string|null> $context
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $field = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
