<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Tests\Support\DocumentXmlGraphs as X;
use Akira\Efatura\Tests\Support\SchemaFixtures;
use Akira\Efatura\Xml\LibxmlSchemaValidator;
use Akira\Efatura\Xml\SchemaViolation;

beforeEach(function (): void {
    $this->schemas = new SchemaFixtures;
});

afterEach(function (): void {
    $this->schemas->remove();
});

it('binds the hardened libxml validator', function (): void {
    expect(resolve(SchemaValidator::class))->toBeInstanceOf(LibxmlSchemaValidator::class);
});

it('accepts the generated document xml', function (): void {
    resolve(SchemaValidator::class)->validate(X::fixture(DocumentType::Invoice));

    expect(libxml_get_errors())->toBe([]);
});

it('reports schema violations with the element but never the value', function (string $search, string $replace, string $element, string $secret): void {
    $xml = str_replace($search, $replace, X::fixture(DocumentType::Invoice));

    expect(fn () => resolve(SchemaValidator::class)->validate($xml))
        ->toThrow(function (SchemaValidationException $exception) use ($element, $secret): void {
            $violations = print_r($exception->violations, true);

            expect($exception->errorCode)->toBe('xml.schema_invalid')
                ->and($exception->getMessage())->toBe('xml.schema_invalid')
                ->and($exception->context)->toBe(['violations' => count($exception->violations)])
                ->and($exception->violations[0])->toBeInstanceOf(SchemaViolation::class)
                ->and($exception->violations[0]->line)->toBe(2)
                ->and($exception->violations[0]->level)->toBe(LIBXML_ERR_ERROR)
                ->and($exception->violations[0]->message)->toStartWith('Element ')
                ->and($exception->violations[0]->message)->toContain('{urn:cv:efatura:xsd:v1.0}' . $element)
                ->and($violations)->not->toContain($secret)
                ->and($exception->getMessage())->not->toContain($secret);
        });
})->with([
    'element out of order' => ['<LedCode>', '<Note>SECRET-NOTE</Note><LedCode>', 'Note', 'SECRET-NOTE'],
    'amount scale'         => ['<PayableAmount>115</PayableAmount>', '<PayableAmount>987654.123456</PayableAmount>', 'PayableAmount', '987654.123456'],
    'specimen false'       => ['DocumentTypeCode="1">', 'DocumentTypeCode="1"><IsSpecimen>false</IsSpecimen>', 'IsSpecimen', 'false'],
    'series with a space'  => ['<Serie>A-1</Serie>', '<Serie>SECRET 31337</Serie>', 'Serie', 'SECRET 31337'],
    'quoted series'        => ['<Serie>A-1</Serie>', "<Serie>Sant\x27Ana secret \x27x\x27 tail</Serie>", 'Serie', 'secret'],
    'attribute value'      => ['LineTypeCode="N"', 'LineTypeCode="Q31337"', 'Line', 'Q31337'],
]);

it('rejects malformed xml without echoing it', function (string $xml, array $messages): void {
    expect(fn () => resolve(SchemaValidator::class)->validate($xml))
        ->toThrow(function (SchemaValidationException $exception) use ($messages): void {
            expect($exception->errorCode)->toBe('xml.malformed')
                ->and(array_column($exception->violations, 'message'))->toBe($messages)
                ->and(array_column($exception->violations, 'level'))->each->toBe(LIBXML_ERR_FATAL);
        });
})->with([
    'empty'            => ['', []],
    'unclosed'         => ['<Dfe><Item>', ['Premature end of data in tag Item line 1']],
    'undefined entity' => ['<Dfe>&secret;</Dfe>', ['Entity \'…\'']],
    'unquoted value'   => ['<Dfe a=secret/>', ['AttValue: "…"', 'attributes construct error', "Couldn\x27t find end of Start Tag Dfe line 1"]],
]);

it('refuses a document type declaration before parsing it', function (string $xml): void {
    $file = $this->schemas->root . '/private.txt';
    $this->schemas->write('private.txt', 'PRIVATE-FILE-CONTENTS');

    expect(fn () => resolve(SchemaValidator::class)->validate(str_replace('FILE', $file, $xml)))
        ->toThrow(function (SchemaValidationException $exception): void {
            expect($exception->errorCode)->toBe('xml.doctype_forbidden')
                ->and($exception->violations)->toBe([])
                ->and(json_encode([$exception->getMessage(), $exception->context], JSON_THROW_ON_ERROR))->not->toContain('PRIVATE-FILE-CONTENTS');
        });
})->with([
    'external entity'  => ['<?xml version="1.0"?><!DOCTYPE Dfe [<!ENTITY x SYSTEM "file://FILE">]><Dfe>&x;</Dfe>'],
    'recursive entity' => ['<!DOCTYPE Dfe [<!ENTITY a "&b;&b;"><!ENTITY b "&a;&a;">]><Dfe>&a;</Dfe>'],
]);

it('refuses a document type declaration hidden by another encoding', function (): void {
    $xml = "\xFF\xFE" . mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE Dfe><Dfe/>', 'UTF-16LE', 'UTF-8');

    expect(fn () => resolve(SchemaValidator::class)->validate($xml))->toThrow(SchemaValidationException::class, 'xml.doctype_forbidden');
});

it('accepts only the root elements of the signature profile', function (string $xml, SignatureProfile $profile): void {
    expect(fn () => resolve(SchemaValidator::class)->validate($xml, $profile))
        ->toThrow(function (SchemaValidationException $exception) use ($profile): void {
            expect($exception->errorCode)->toBe('xml.schema_invalid')
                ->and($exception->violations)->toHaveCount(1)
                ->and($exception->violations[0]->level)->toBe(LIBXML_ERR_ERROR)
                ->and($exception->violations[0]->message)->toEndWith(" is not a root element of the {$profile->value} profile.")
                ->and(print_r($exception->violations, true))->not->toContain('SECRET');
        });
})->with([
    'discount'              => ['<Discount xmlns="urn:cv:efatura:xsd:v1.0" ValueType="P">10</Discount>', SignatureProfile::Enveloped],
    'note'                  => ['<Note xmlns="urn:cv:efatura:xsd:v1.0">SECRET customer delivery note</Note>', SignatureProfile::Enveloped],
    'signature object'      => ['<ds:Object xmlns:ds="http://www.w3.org/2000/09/xmldsig#">SECRET</ds:Object>', SignatureProfile::Enveloped],
    'document not detached' => [fn (): string => X::fixture(DocumentType::Invoice), SignatureProfile::InternallyDetached],
]);

it('validates against the profile it is given', function (): void {
    $detached = SchemaFixtures::official('1 Invoice - InternallyDetachedSignature.xml');

    expect(fn () => resolve(SchemaValidator::class)->validate($detached, SignatureProfile::Enveloped))
        ->toThrow(SchemaValidationException::class, 'xml.schema_invalid');

    resolve(SchemaValidator::class)->validate($detached, SignatureProfile::InternallyDetached);
});

it('never validates against an included schema whose bytes changed', function (string $change, string $errorCode): void {
    $this->schemas->copyOfficial();
    $path = $this->schemas->root . '/' . SchemaFixtures::XSD . 'common/CV_EFatura_Types_v1.0.xsd';
    file_put_contents($path, $change === 'append' ? file_get_contents($path) . ' ' : strrev((string) file_get_contents($path)));

    expect(fn () => $this->schemas->validator()->validate(X::fixture(DocumentType::Invoice)))
        ->toThrow(OfficialArtifactException::class, $errorCode);
})->with([
    'size'     => ['append', 'artifacts.size_mismatch'],
    'checksum' => ['reverse', 'artifacts.checksum_mismatch'],
]);

it('refuses every resource the manifest does not list', function (string $location): void {
    $this->schemas->custom([
        'schemas/entry.xsd' => '<x:schema xmlns:x="http://www.w3.org/2001/XMLSchema"><x:include schemaLocation="' . $location . '"/>'
            . '<x:element name="Dfe"/></x:schema>',
    ]);
    $this->schemas->write('schemas/unlisted.xsd', '<x:schema xmlns:x="http://www.w3.org/2001/XMLSchema"/>');

    expect(fn () => $this->schemas->validator()->validate('<Dfe/>'))->toThrow(function (SchemaValidationException $exception): void {
        expect($exception->errorCode)->toBe('xml.external_resource')
            ->and($exception->violations)->toBe([])
            ->and($exception->getMessage())->not->toContain($this->schemas->root);
    });
})->with([
    'network'          => ['http://127.0.0.1:9/remote.xsd'],
    'outside the root' => ['../../efatura-outside.xsd'],
    'unlisted file'    => ['unlisted.xsd'],
]);
