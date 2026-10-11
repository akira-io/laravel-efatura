<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

final readonly class EntryResult
{
    /**
     * @param list<RemoteMessage> $messages
     */
    public function __construct(
        public string $entryName,
        public string $identifier,
        public bool $succeeded,
        public ?string $authorizationCode,
        public ?string $signatureTimeStamp,
        public array $messages,
    ) {}
}
