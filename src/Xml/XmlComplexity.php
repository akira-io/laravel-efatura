<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use XMLReader;

use const LIBXML_NONET;

final class XmlComplexity
{
    public const int MAX_ELEMENTS = 250_000;

    public const int MAX_NAMESPACE_DECLARATIONS = 1_000;

    public static function exceeded(string $xml): bool
    {
        $reader       = XMLReader::fromString($xml, flags: LIBXML_NONET);
        $elements     = 0;
        $declarations = 0;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            $elements++;
            $declarations += self::declarations($reader);
            if ($elements > self::MAX_ELEMENTS || $declarations > self::MAX_NAMESPACE_DECLARATIONS) {
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
