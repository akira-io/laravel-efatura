<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Akira\Efatura\Sequence\SequenceScope;
use Throwable;

final class SequenceException extends EfaturaException
{
    private function __construct(string $errorCode, SequenceScope $scope, ?Throwable $previous = null, bool $retryable = false)
    {
        parent::__construct($errorCode, $errorCode, context: [
            'emitterTaxId'     => $scope->emitterTaxId,
            'fiscalYear'       => $scope->year,
            'ledCode'          => $scope->ledCode,
            'documentTypeCode' => $scope->documentType->code(),
        ], previous: $previous, retryable: $retryable);
    }

    public static function exhausted(SequenceScope $scope): self
    {
        return new self('sequence.exhausted', $scope);
    }

    public static function insideTransaction(SequenceScope $scope): self
    {
        return new self('sequence.inside_transaction', $scope);
    }

    public static function unavailable(SequenceScope $scope, ?Throwable $previous = null): self
    {
        return new self('sequence.unavailable', $scope, $previous, retryable: true);
    }
}
