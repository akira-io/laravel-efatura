<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Transport\RemoteMessage;

final class TransportException extends EfaturaException
{
    /**
     * @param array<string, int|string> $context
     * @param list<RemoteMessage>       $messages
     */
    private function __construct(public readonly TransportFailure $failure, array $context, ?string $field, public readonly array $messages = [])
    {
        parent::__construct($failure->value, $failure->value, $field, $context, retryable: $failure->retryable());
    }

    public static function because(
        TransportFailure $failure,
        PackagedArchive|string|null $subject = null,
        ?int $status = null,
        ?int $bytes = null,
        ?string $field = null,
    ): self {
        $context = [
            'status'      => $status,
            'kind'        => $subject instanceof PackagedArchive ? $subject->kind->value : null,
            'identifiers' => $subject instanceof PackagedArchive ? implode(',', $subject->identifiers()) : $subject,
            'bytes'       => $bytes,
        ];

        return new self($failure, array_filter($context, static fn (int|string|null $value): bool => $value !== null), $field);
    }

    /**
     * @param list<RemoteMessage> $messages
     */
    public static function rejected(array $messages, int $status): self
    {
        return new self(TransportFailure::Rejected, ['status' => $status], null, $messages);
    }
}
