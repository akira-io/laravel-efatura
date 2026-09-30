<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class EfaturaValidationException extends EfaturaException
{
    public function __construct(
        string $field,
        string $message,
    ) {
        parent::__construct('validation.invalid_value', $message, $field);
    }

    public function field(): string
    {
        return (string) $this->field;
    }
}
