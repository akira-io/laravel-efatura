<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Throwable;

final class StorageException extends EfaturaException
{
    /**
     * @param array<string, string> $context
     */
    private function __construct(string $errorCode, ?string $field = null, array $context = [], ?Throwable $previous = null, bool $retryable = false)
    {
        parent::__construct($errorCode, $errorCode, $field, $context, $previous, $retryable);
    }

    public static function invalidIud(): self
    {
        return new self('storage.invalid_iud', 'iud');
    }

    public static function notAuthorized(string $iud): self
    {
        return new self('storage.not_authorized', context: ['iud' => $iud]);
    }

    public static function dfaMismatch(string $iud, string $dfaIud, string $model): self
    {
        return new self('storage.dfa_mismatch', 'dfa', ['iud' => $iud, 'dfaIud' => $dfaIud, 'model' => $model]);
    }

    public static function diskInvalid(string $disk, Throwable $previous): self
    {
        return new self('storage.disk_invalid', 'efatura.storage.disk', ['disk' => $disk], $previous);
    }

    public static function unavailable(string $path, ?Throwable $previous = null): self
    {
        return new self('storage.unavailable', context: ['path' => $path], previous: $previous, retryable: true);
    }

    public static function conflict(string $path): self
    {
        return new self('storage.conflict', context: ['path' => $path]);
    }

    public static function missing(string $path): self
    {
        return new self('storage.missing', context: ['path' => $path]);
    }

    public static function corrupted(string $path): self
    {
        return new self('storage.corrupted', context: ['path' => $path]);
    }
}
