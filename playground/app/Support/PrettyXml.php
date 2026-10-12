<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;

final class PrettyXml
{
    public static function format(string $xml): string
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        return $document->loadXML($xml, LIBXML_NONET) ? (string) $document->saveXML() : $xml;
    }
}
