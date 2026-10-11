<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
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
            $root = $this->parser->parse($xml)->documentElement?->localName;
        } catch (SchemaValidationException $schemaValidationException) {
            return XmlValidation::refused($schemaValidationException);
        }

        $profile ??= $root === Fiscal::DETACHED_SIGNATURE_ROOT ? SignatureProfile::InternallyDetached : SignatureProfile::Enveloped;

        try {
            $this->schemas->validate($xml, $profile);
            $failure = null;
        } catch (EfaturaException $efaturaException) {
            $failure = $efaturaException;
        }

        return XmlValidation::checked($profile, $failure, $this->signatures->handle($xml));
    }
}
