<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Support\OfficialArtifacts;
use DOMDocument;
use DOMDocumentType;

use const LIBXML_NONET;

final readonly class LibxmlSchemaValidator implements SchemaValidator
{
    public function __construct(private OfficialArtifacts $artifacts) {}

    public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
    {
        if (str_contains($xml, '<!DOCTYPE')) {
            throw SchemaValidationException::doctypeForbidden();
        }

        $schema         = $this->artifacts->xsdEntry($profile->value);
        $internalErrors = libxml_use_internal_errors(true);
        $pending        = \count(libxml_get_errors());
        $loader         = libxml_get_external_entity_loader();
        $refusal        = null;

        libxml_set_external_entity_loader(function (?string $public, string $system) use (&$refusal): ?string {
            try {
                return $this->artifacts->located($system);
            } catch (OfficialArtifactException $officialArtifactException) {
                $refusal ??= $officialArtifactException;

                return null;
            }
        });

        try {
            $document = $this->parse($xml, $pending);
            $valid    = @$document->schemaValidate($schema, LIBXML_NONET);
            if ($refusal?->errorCode === 'artifacts.unknown_or_unsafe_path') {
                throw SchemaValidationException::externalResource();
            }

            if ($refusal instanceof OfficialArtifactException) {
                throw $refusal;
            }

            if (! $valid) {
                throw SchemaValidationException::schemaInvalid($this->violations($pending));
            }
        } finally {
            libxml_set_external_entity_loader($loader);
            if ($pending === 0) {
                libxml_clear_errors();
            }

            libxml_use_internal_errors($internalErrors);
        }
    }

    private function parse(string $xml, int $pending): DOMDocument
    {
        $document = new DOMDocument;
        if ($xml === '' || ! $document->loadXML($xml, LIBXML_NONET)) {
            throw SchemaValidationException::malformed($this->violations($pending));
        }

        if ($document->doctype instanceof DOMDocumentType) {
            throw SchemaValidationException::doctypeForbidden();
        }

        return $document;
    }

    /**
     * @return list<SchemaViolation>
     */
    private function violations(int $pending): array
    {
        return array_map(SchemaViolation::fromLibxml(...), \array_slice(libxml_get_errors(), $pending));
    }
}
