<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Packaging\PackagedArchive;

interface Packager
{
    /**
     * @param list<string> $signedXml
     */
    public function package(array $signedXml): PackagedArchive;
}
