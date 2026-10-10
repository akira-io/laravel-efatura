<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Exceptions\SchemaValidationException;
use DOMDocument;
use DOMDocumentType;

use const LIBXML_NONET;
use const LIBXML_RECOVER;

final readonly class SafeXmlParser
{
    public function parse(string $xml): DOMDocument
    {
        if (str_contains($xml, '<!DOCTYPE')) {
            throw SchemaValidationException::doctypeForbidden();
        }

        $internalErrors = libxml_use_internal_errors(true);
        $pending        = \count(libxml_get_errors());

        try {
            return self::load($xml, $pending);
        } finally {
            if ($pending === 0) {
                libxml_clear_errors();
            }

            libxml_use_internal_errors($internalErrors);
        }
    }

    private static function load(string $xml, int $pending): DOMDocument
    {
        $document = new DOMDocument;
        if ($xml === '' || ! $document->loadXML($xml, LIBXML_NONET)) {
            $violations = array_map(SchemaViolation::fromLibxml(...), \array_slice(libxml_get_errors(), $pending));

            throw self::declaresDocumentType($xml) ? SchemaValidationException::doctypeForbidden() : SchemaValidationException::malformed($violations);
        }

        if ($document->doctype instanceof DOMDocumentType) {
            throw SchemaValidationException::doctypeForbidden();
        }

        return $document;
    }

    private static function declaresDocumentType(string $xml): bool
    {
        $document = new DOMDocument;

        return $xml !== '' && @$document->loadXML($xml, LIBXML_NONET | LIBXML_RECOVER) && $document->doctype instanceof DOMDocumentType;
    }
}
