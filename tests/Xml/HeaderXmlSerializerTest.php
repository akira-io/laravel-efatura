<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\XmlFragment;
use Akira\Efatura\Xml\Serializers\HeaderXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use Carbon\CarbonImmutable;

it('writes a self-billed isolated act header in schema order', function (): void {
    $header = DocumentHeaderData::from([...DocumentPayloads::allocatedHeader(), 'selfBilling' => DocumentPayloads::selfBilling()]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->header($xml, $root, $header, 'header')))->toBe(
        '<IsIsolatedAct>true</IsIsolatedAct><SelfBilling><AuthorizationId>12345678-1234-1234-1234-123456789abc</AuthorizationId>'
        . '<AuthorizationCode>1234</AuthorizationCode></SelfBilling><LedCode>99999</LedCode><Serie>A-1</Serie><DocumentNumber>999999999</DocumentNumber>'
        . '<InnerDocumentNumber>INV-1</InnerDocumentNumber><IssueDate>2026-10-02</IssueDate><IssueTime>12:00:00</IssueTime>',
    );
});

it('writes a minimal allocated header', function (): void {
    $header = DocumentHeaderData::from(DocumentPayloads::header(['serie' => 'A', 'documentNumber' => 1]));

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->header($xml, $root, $header, 'header')))
        ->toBe('<LedCode>1</LedCode><Serie>A</Serie><DocumentNumber>1</DocumentNumber><IssueDate>2026-10-02</IssueDate><IssueTime>12:00:00</IssueTime>');
});

it('writes an explicit false isolated act indication', function (): void {
    $header = DocumentHeaderData::from(DocumentPayloads::allocatedHeader(['isIsolatedAct' => false]));

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->header($xml, $root, $header, 'header')))
        ->toStartWith('<IsIsolatedAct>false</IsIsolatedAct><LedCode>');
});

it('writes the issue instant in Cabo Verde time', function (): void {
    $instant = CarbonImmutable::parse('2026-10-03 00:30:00', 'UTC');
    $header  = DocumentHeaderData::from(DocumentPayloads::header(['issueDate' => $instant, 'issueTime' => $instant, 'serie' => 'A', 'documentNumber' => 1]));

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->header($xml, $root, $header, 'header')))
        ->toContain('<IssueDate>2026-10-02</IssueDate><IssueTime>23:30:00</IssueTime>');
});

it('requires the series and number a draft header leaves out', function (array $overrides, string $field): void {
    $header = DocumentHeaderData::from(DocumentPayloads::header($overrides));

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->header($xml, $root, $header, 'header')))
        ->toFailValidationOn($field, 'The ' . $field . ' is required to write the XML document.');
})->with([
    'series'          => [['documentNumber' => 1], 'header.serie'],
    'document number' => [['serie' => 'A'], 'header.documentNumber'],
]);

it('writes the note and extra fields in their own or the default namespace', function (): void {
    $footer = DocumentFooterData::from(['note' => 'Thank you & goodbye', 'extraFields' => [
        ['name' => 'QualquerCampo', 'value' => 'a < b'],
        ['name' => 'Branch', 'value' => '12', 'namespace' => 'urn:example:erp'],
        ['name' => 'Flag', 'value' => ''],
    ]]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->footer($xml, $root, $footer, 'footer')))->toBe(
        '<Note>Thank you &amp; goodbye</Note><ExtraFields><QualquerCampo>a &lt; b</QualquerCampo>'
        . '<Branch xmlns="urn:example:erp">12</Branch><Flag></Flag></ExtraFields>',
    );
});

it('writes a footer with a note alone', function (): void {
    $footer = DocumentFooterData::from(['note' => 'Thank you for your order']);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->footer($xml, $root, $footer, 'footer')))
        ->toBe('<Note>Thank you for your order</Note>');
});

it('writes nothing for an absent footer', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->footer($xml, $root, null, 'footer')))->toBe('');
});

it('reports invalid extra field text on its wire path', function (): void {
    $footer = new DocumentFooterData(extraFields: [new ExtraFieldData('Flag', 'ok'), new ExtraFieldData('Other', "\x0C")]);

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root) => resolve(HeaderXmlSerializer::class)->footer($xml, $root, $footer, 'footer')))
        ->toFailValidationOn('footer.extraFields.1.value', 'The footer.extraFields.1.value contains characters that XML 1.0 does not allow.');
});
