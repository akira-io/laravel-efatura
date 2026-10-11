<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Throwable;

final class DfaException extends EfaturaException
{
    /**
     * @param array<string, string> $context
     */
    private function __construct(string $errorCode, ?string $field = null, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($errorCode, $errorCode, $field, $context, $previous);
    }

    public static function notAuthorized(string $iud): self
    {
        return new self('dfa.not_authorized', context: ['iud' => $iud]);
    }

    public static function invalidIud(): self
    {
        return new self('dfa.invalid_iud', 'iud');
    }

    public static function iucRequired(string $iud): self
    {
        return new self('dfa.iuc_required', 'iuc', ['iud' => $iud]);
    }

    public static function invalidIuc(): self
    {
        return new self('dfa.invalid_iuc', 'iuc');
    }

    public static function iucMismatch(string $iud): self
    {
        return new self('dfa.iuc_mismatch', 'iuc', ['iud' => $iud]);
    }

    public static function rendererUnavailable(string $package): self
    {
        return new self('dfa.renderer_unavailable', context: ['package' => $package]);
    }

    public static function cacheUnavailable(?Throwable $previous = null): self
    {
        return new self('dfa.cache_unavailable', 'efatura.dfa.cache_path', previous: $previous);
    }
}
