<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

final readonly class RenderedDfa
{
    public const string CONTENT_TYPE = 'application/pdf';

    public function __construct(
        public string $bytes,
        public string $filename,
        public string $contentType = self::CONTENT_TYPE,
    ) {}
}
