<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ValidateXmlDocumentAction;
use Akira\Efatura\Contracts\DocumentSchemaValidator;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Xml\SchemaViolation;
use Akira\Efatura\Xml\XmlValidation;

function validateXmlDocument(string $xml, ?SignatureProfile $profile = null): XmlValidation
{
    return resolve(ValidateXmlDocumentAction::class)->handle($xml, $profile);
}

it('detects the profile from the root element and accepts a signed document', function (string $kind, SignatureProfile $profile): void {
    $validation = validateXmlDocument(S::sign(S::unsigned($kind), $profile)->xml);

    expect($validation->valid())->toBeTrue()
        ->and($validation->profile)->toBe($profile)
        ->and($validation->schemaCode)->toBeNull()
        ->and($validation->violations)->toBe([])
        ->and($validation->signature)->toBeInstanceOf(SignatureVerification::class)
        ->and($validation->signature?->valid)->toBeTrue();
})->with(S::documentsAndProfiles());

it('validates against the profile it is given and leaves the signature unchecked when the schema fails', function (): void {
    $validation = validateXmlDocument(S::sign(S::unsigned(), SignatureProfile::InternallyDetached)->xml, SignatureProfile::Enveloped);

    expect($validation->valid())->toBeFalse()
        ->and($validation->profile)->toBe(SignatureProfile::Enveloped)
        ->and($validation->schemaCode)->toBe('xml.schema_invalid')
        ->and($validation->violations)->not->toBe([])
        ->and($validation->signature)->toBeNull()
        ->and($validation->toArray())->toMatchArray(['valid' => false, 'profile' => 'enveloped', 'signature' => null]);
});

it('refuses xml the hardened parser does not accept without verifying a signature', function (string $xml, string $code): void {
    $validation = validateXmlDocument($xml);

    expect($validation->valid())->toBeFalse()
        ->and($validation->profile)->toBeNull()
        ->and($validation->schemaCode)->toBe($code)
        ->and($validation->signature)->toBeNull()
        ->and($validation->toArray())->toMatchArray(['valid' => false, 'profile' => null, 'signature' => null, 'trust_chain_verified' => false]);
})->with([
    'malformed xml'  => ['<Dfe><unclosed></Dfe>', 'xml.malformed'],
    'an xxe attempt' => ['<?xml version="1.0"?><!DOCTYPE Dfe [<!ENTITY x SYSTEM "file:///etc/passwd">]><Dfe>&x;</Dfe>', 'xml.doctype_forbidden'],
]);

it('keeps only the code of a package exception raised by the schema validator', function (): void {
    app()->instance(SchemaValidator::class, new class implements SchemaValidator
    {
        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            throw new ConfigurationException('configuration.invalid_type', 'efatura.certificates.passphrase');
        }
    });

    $validation = validateXmlDocument(S::sign(S::unsigned())->xml);

    expect($validation->valid())->toBeFalse()
        ->and($validation->signature)->toBeNull()
        ->and($validation->toArray()['schema'])->toBe(['valid' => false, 'code' => 'configuration.invalid_type', 'violations' => [], 'violations_total' => 0])
        ->and(json_encode($validation->toArray(), JSON_THROW_ON_ERROR))->not->toContain('passphrase');
});

it('hands the schema validator the document it already parsed when the validator accepts one', function (): void {
    $validator = new class implements DocumentSchemaValidator
    {
        public ?DOMDocument $document = null;

        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            throw new RuntimeException('The parsed document was not used.');
        }

        public function validateDocument(DOMDocument $document, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            $this->document = $document;
        }
    };
    app()->instance(SchemaValidator::class, $validator);

    $validation = validateXmlDocument(S::sign(S::unsigned())->xml);

    expect($validation->valid())->toBeTrue()
        ->and($validator->document?->documentElement?->localName)->toBe('Dfe');
});

it('lets a fault of the bundled official artifacts reach the caller', function (): void {
    app()->instance(SchemaValidator::class, new class implements SchemaValidator
    {
        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            throw new OfficialArtifactException('artifacts.missing_or_unreadable', 'resolve');
        }
    });

    expect(fn (): XmlValidation => validateXmlDocument(S::sign(S::unsigned())->xml))
        ->toThrow(OfficialArtifactException::class, 'artifacts.missing_or_unreadable');
});

it('presents the result as plain values', function (): void {
    $signed = S::sign(S::unsigned(), SignatureProfile::InternallyDetached);

    expect(validateXmlDocument($signed->xml)->toArray())->toBe([
        'valid'     => true,
        'profile'   => 'detached',
        'schema'    => ['valid' => true, 'code' => null, 'violations' => [], 'violations_total' => 0],
        'signature' => [
            'valid'       => true,
            'failures'    => [],
            'certificate' => [
                'subject'       => 'CN=e-Fatura Test Signer,O=e-Fatura Test,C=CV',
                'serial_number' => '1715004',
                'digest'        => $signed->certificateDigest,
            ],
        ],
        'trust_chain_verified' => false,
    ]);
});

it('presents a missing signature without a certificate', function (): void {
    $validation = validateXmlDocument(S::unsigned());

    expect($validation->signature?->failures)->toBe([SignatureFailure::Missing])
        ->and($validation->toArray()['signature'])->toBe(['valid' => false, 'failures' => ['signature.missing'], 'certificate' => null]);
});

it('names the profiles it accepts', function (): void {
    expect(XmlValidation::PROFILES)->toBe(['enveloped' => SignatureProfile::Enveloped, 'detached' => SignatureProfile::InternallyDetached]);
});

it('presents the first hundred violations and counts them all', function (int $count, int $presented): void {
    $violations = array_map(static fn (int $line): SchemaViolation => new SchemaViolation($line, 1, LIBXML_ERR_ERROR, 'violation ' . $line), range(1, $count));
    $schema     = XmlValidation::refused(SchemaValidationException::malformed($violations))->toArray()['schema'];

    expect($schema['violations'])->toHaveCount($presented)
        ->and($schema['violations_total'])->toBe($count)
        ->and(array_column($schema['violations'], 'line'))->toBe(range(1, $presented));
})->with([
    'one'          => [1, 1],
    'exactly 100'  => [XmlValidation::MAX_PRESENTED_VIOLATIONS, 100],
    'one too many' => [XmlValidation::MAX_PRESENTED_VIOLATIONS + 1, 100],
    'many more'    => [250, 100],
]);
