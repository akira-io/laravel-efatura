<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\DocumentSchemaValidator;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use Akira\Efatura\Xml\XmlValidation;

final readonly class ValidateXmlDocumentAction
{
    public function __construct(private SafeXmlParser $parser, private SchemaValidator $schemas, private VerifyXmlSignatureAction $signatures) {}

    public function handle(string $xml, ?SignatureProfile $profile = null): XmlValidation
    {
        try {
            $document = $this->parser->parse($xml);
        } catch (SchemaValidationException $schemaValidationException) {
            return XmlValidation::refused($schemaValidationException);
        }

        $root = $document->documentElement?->localName;
        $profile ??= $root === Fiscal::DETACHED_SIGNATURE_ROOT ? SignatureProfile::InternallyDetached : SignatureProfile::Enveloped;

        try {
            $this->schemas instanceof DocumentSchemaValidator
                ? $this->schemas->validateDocument($document, $profile)
                : $this->schemas->validate($xml, $profile);
            $failure = null;
        } catch (OfficialArtifactException $officialArtifactException) {
            throw $officialArtifactException;
        } catch (EfaturaException $efaturaException) {
            $failure = $efaturaException;
        }

        return XmlValidation::checked($profile, $failure, $failure instanceof EfaturaException ? null : $this->signatures->handle($document));
    }
}
