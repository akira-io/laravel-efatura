<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Packaging\PreparedEvent;
use Akira\Efatura\Sequence\NumberedDocument;
use Throwable;

final class IssuanceException extends EfaturaException
{
    /**
     * @param array<string, int|string|null> $context
     */
    private function __construct(string $errorCode, array $context, Throwable $previous)
    {
        parent::__construct($errorCode, $errorCode, context: $context, previous: $previous);
    }

    public static function abortedBeforeSubmission(PreparedDocument|PreparedEvent $prepared, Throwable $previous): self
    {
        $context = $prepared instanceof PreparedDocument
            ? new NumberedDocument($prepared->document, $prepared->iud, $prepared->allocated)->context()
            : ['eventId' => $prepared->eventId, 'eventTypeCode' => $prepared->event->eventType->value];

        return new self('issuance.aborted_before_submission', $context, $previous);
    }
}
