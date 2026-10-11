<?php

declare(strict_types=1);

namespace Akira\Efatura\Storage;

use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SubmissionResult;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class ResponseEvidence
{
    public function encode(EntryResult $entry, SubmissionResult $submission): string
    {
        return json_encode([
            'entryName'          => $entry->entryName,
            'identifier'         => $entry->identifier,
            'succeeded'          => $entry->succeeded,
            'authorizationCode'  => $entry->authorizationCode,
            'signatureTimeStamp' => $entry->signatureTimeStamp,
            'messages'           => array_map(static fn (RemoteMessage $message): array => [
                'source'      => $message->source,
                'type'        => $message->type,
                'dateTime'    => $message->dateTime,
                'code'        => $message->code,
                'description' => $message->description,
                'location'    => $message->location,
            ], $entry->messages),
            'packageKind' => $submission->kind->value,
            'httpStatus'  => $submission->status,
            'receivedAt'  => $submission->receivedAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
