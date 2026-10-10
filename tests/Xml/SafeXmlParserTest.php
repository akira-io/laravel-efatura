<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Xml\SafeXmlParser;

afterEach(function (): void {
    libxml_clear_errors();
    libxml_use_internal_errors(false);
});

it('parses a well formed document without formatting it', function (): void {
    $document = new SafeXmlParser()->parse('<?xml version="1.0" encoding="UTF-8"?><Dfe xmlns="urn:cv:efatura:xsd:v1.0" Id="A"><Item>1</Item></Dfe>');

    expect($document->documentElement?->localName)->toBe('Dfe')
        ->and($document->documentElement?->getAttribute('Id'))->toBe('A')
        ->and($document->saveXML($document->documentElement))->toBe('<Dfe xmlns="urn:cv:efatura:xsd:v1.0" Id="A"><Item>1</Item></Dfe>');
});

it('reports malformed xml with redacted violations and never the document', function (string $xml): void {
    expect(fn (): DOMDocument => new SafeXmlParser()->parse($xml))->toThrow(function (SchemaValidationException $exception): void {
        expect($exception->errorCode)->toBe('xml.malformed')
            ->and($exception->getMessage())->toBe('xml.malformed')
            ->and(json_encode($exception->violations, JSON_THROW_ON_ERROR))->not->toContain('secret-value');
    });
})->with([
    'empty'          => [''],
    'unclosed'       => ['<Dfe>secret-value'],
    'unquoted value' => ['<Dfe a=secret-value/>'],
]);

it('refuses a document type declaration in any encoding', function (string $xml): void {
    expect(fn (): DOMDocument => new SafeXmlParser()->parse($xml))->toThrow(SchemaValidationException::class, 'xml.doctype_forbidden');
})->with([
    'utf-8 declaration'  => ['<!DOCTYPE Dfe [<!ENTITY a "secret-value">]><Dfe>&a;</Dfe>'],
    'utf-16 declaration' => ["\xFF\xFE" . mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE Dfe []><Dfe/>', 'UTF-16LE', 'UTF-8')],
]);

it('keeps the errors the caller left pending', function (): void {
    libxml_use_internal_errors(true);
    new DOMDocument()->loadXML('<pending>');
    $pending = libxml_get_errors();

    expect(fn (): DOMDocument => new SafeXmlParser()->parse('<Dfe>'))->toThrow(SchemaValidationException::class, 'xml.malformed')
        ->and(libxml_use_internal_errors())->toBeTrue()
        ->and(array_slice(libxml_get_errors(), 0, count($pending)))->toEqual($pending);
});

it('leaves no error behind when the caller had none', function (): void {
    expect(fn (): DOMDocument => new SafeXmlParser()->parse('<Dfe>'))->toThrow(SchemaValidationException::class, 'xml.malformed')
        ->and(libxml_get_errors())->toBe([])
        ->and(libxml_use_internal_errors())->toBeFalse();
});
