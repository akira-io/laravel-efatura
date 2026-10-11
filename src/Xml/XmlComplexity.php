<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use XMLReader;

use const LIBXML_NONET;

final class XmlComplexity
{
    public const int MAX_ELEMENTS = 100_000;

    public const int MAX_NAMESPACES_IN_SCOPE = 16;

    public static function exceeded(string $xml): bool
    {
        $reader   = XMLReader::fromString($xml, flags: LIBXML_NONET);
        $elements = 0;
        $inScope  = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            $elements++;
            $depth           = $reader->depth;
            $inScope[$depth] = ($inScope[$depth - 1] ?? 0) + self::declarations($reader);
            if ($elements > self::MAX_ELEMENTS || $inScope[$depth] > self::MAX_NAMESPACES_IN_SCOPE) {
                return true;
            }
        }

        return false;
    }

    private static function declarations(XMLReader $reader): int
    {
        $declarations = 0;
        while ($reader->moveToNextAttribute()) {
            $declarations += $reader->name === 'xmlns' || str_starts_with($reader->name, 'xmlns:') ? 1 : 0;
        }

        return $declarations;
    }
}
