<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Xml\XmlValidation;

final readonly class RoutesConfig
{
    public const int MAX_UPLOAD_KILOBYTES = XmlValidation::MAX_BYTES / 1024;

    /**
     * @param list<string> $middleware
     */
    public function __construct(
        public bool $enabled = false,
        public string $prefix = 'efatura',
        public string $name = 'efatura.',
        public array $middleware = [],
        public int $maxUploadKilobytes = self::MAX_UPLOAD_KILOBYTES,
        public bool $remoteReadiness = false,
    ) {}
}
