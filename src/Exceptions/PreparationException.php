<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Akira\Efatura\Sequence\NumberedDocument;
use Throwable;

final class PreparationException extends EfaturaException
{
    private function __construct(string $errorCode, array $context, Throwable $previous)
    {
        parent::__construct($errorCode, $errorCode, context: $context, previous: $previous);
    }

    public static function failedAfterAllocation(NumberedDocument $numbered, Throwable $previous): self
    {
        return new self('preparation.failed_after_allocation', $numbered->context(), $previous);
    }
}
