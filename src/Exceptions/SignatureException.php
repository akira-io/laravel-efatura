<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class SignatureException extends EfaturaException
{
    public function __construct(string $errorCode)
    {
        parent::__construct($errorCode, $errorCode);
    }
}
