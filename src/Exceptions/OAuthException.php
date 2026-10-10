<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class OAuthException extends EfaturaException
{
    /**
     * @param array<string, int|string> $context
     */
    private function __construct(string $errorCode, array $context)
    {
        parent::__construct($errorCode, $errorCode, context: $context);
    }

    public static function refreshTokenMissing(string $emitterTaxId): self
    {
        return new self('oauth.refresh_token_missing', ['emitter' => $emitterTaxId]);
    }

    public static function tokenRefused(string $emitterTaxId, int $status): self
    {
        return new self('oauth.token_refused', ['emitter' => $emitterTaxId, 'status' => $status]);
    }

    public static function unexpectedResponse(string $emitterTaxId, int $status): self
    {
        return new self('oauth.unexpected_response', ['emitter' => $emitterTaxId, 'status' => $status]);
    }

    public static function discoveryFailed(?int $status = null): self
    {
        return new self('oauth.discovery_failed', $status === null ? [] : ['status' => $status]);
    }
}
