<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use RuntimeException;

final class EfaturaValidationException extends RuntimeException
{
    public function __construct(
        private readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
