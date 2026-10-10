<?php

declare(strict_types=1);

namespace Akira\Efatura\Packaging;

use Akira\Efatura\Data\EventData;
use Akira\Efatura\Signing\SignedXml;

final readonly class PreparedEvent
{
    public function __construct(
        public EventData $event,
        public string $eventId,
        public string $unsignedXml,
        public SignedXml $signed,
        public PackagedArchive $archive,
    ) {}
}
