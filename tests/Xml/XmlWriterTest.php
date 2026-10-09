<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Xml\XmlWriter;
use Brick\Math\BigDecimal;

it('writes children in the default fiscal namespace without prefixes or redeclarations', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    $header = $xml->container($root, 'Invoice');
    $xml->element($header, 'LedCode', '1', 'header.ledCode');

    expect($xml->toXml())->toBe('<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<Dfe xmlns="' . Fiscal::XML_NAMESPACE . '"><Invoice><LedCode>1</LedCode></Invoice></Dfe>' . "\n")
        ->not->toContain('xmlns:')
        ->and(substr_count($xml->toXml(), 'xmlns='))->toBe(1);
});

it('escapes markup characters in text and attributes', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    $name = $xml->element($root, 'Name', '<A & "B" \'C\'>', 'emitter.name');
    $xml->attribute($name, 'CountryCode', '<"&\'>', 'emitter.taxId.countryCode');

    expect($xml->toXml())->toContain('<Name CountryCode="&lt;&quot;&amp;\'&gt;">&lt;A &amp; "B" \'C\'&gt;</Name>');
});

it('preserves Cabo Verde names byte for byte in UTF-8', function (string $name): void {
    $xml = new XmlWriter;
    $xml->element($xml->document('Dfe'), 'City', $name, 'emitter.address.city');

    expect($xml->toXml())->toContain('<City>' . $name . '</City>');
})->with(['São Filipe', 'Ribeira Grande de Santiago', 'Ação', 'Tarrafal de São Nicolau']);

it('rejects text outside the XML 1.0 character range on its field path', function (string $text): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect(fn (): ?DOMElement => $xml->element($root, 'Name', 'Emitter' . $text, 'emitter.name'))
        ->toFailValidationOn('emitter.name', 'The emitter.name contains characters that XML 1.0 does not allow.');
})->with([
    'null byte'            => "\x00",
    'start of heading'     => "\x01",
    'vertical tab'         => "\x0B",
    'unit separator'       => "\x1F",
    'noncharacter FFFE'    => "\u{FFFE}",
    'invalid UTF-8'        => "\xC3\x28",
    'lone UTF-8 lead byte' => "\xE2\x82",
]);

it('rejects attribute values outside the XML 1.0 character range on their field path', function (): void {
    $xml     = new XmlWriter;
    $element = $xml->container($xml->document('Dfe'), 'TaxId');

    expect(function () use ($xml, $element): void {
        $xml->attribute($element, 'CountryCode', "C\x00V", 'emitter.taxId.countryCode');
    })
        ->toFailValidationOn('emitter.taxId.countryCode', 'The emitter.taxId.countryCode contains characters that XML 1.0 does not allow.');
});

it('keeps tab, line feed and carriage return', function (): void {
    $xml = new XmlWriter;
    $xml->element($xml->document('Dfe'), 'Note', "a\tb\nc\rd", 'footer.note');

    expect($xml->toXml())->toContain("<Note>a\tb\nc&#13;d</Note>");
});

it('writes nothing for an absent value', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    $xml->attribute($root, 'Version', null, 'version');

    expect($xml->element($root, 'Note', null, 'footer.note'))->toBeNull()
        ->and($xml->toXml())->toContain('<Dfe xmlns="' . Fiscal::XML_NAMESPACE . '"/>');
});

it('refuses to write an empty fiscal element', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect(fn (): ?DOMElement => $xml->element($root, 'Serie', '', 'header.serie'))
        ->toFailValidationOn('header.serie', 'The header.serie is required to write the XML document.');
});

it('requires a valued element on its field path', function (?string $value): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect($xml->requiredElement($root, 'Serie', 'A-1', 'header.serie')->textContent)->toBe('A-1')
        ->and(fn (): DOMElement => $xml->requiredElement($root, 'Serie', $value, 'header.serie'))
        ->toFailValidationOn('header.serie', 'The header.serie is required to write the XML document.');
})->with(['absent' => null, 'empty' => '']);

it('writes optional elements in the given order with their property paths', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    $xml->elements($root, 'emitter.contacts', ['Telephone' => ['telephone', '1234567'], 'Telefax' => ['telefax', null], 'Email' => ['email', 'a@b.cv']]);

    expect($xml->toXml())->toContain('<Telephone>1234567</Telephone><Email>a@b.cv</Email>')
        ->and(fn () => $xml->elements($root, 'emitter.contacts', ['Website' => ['website', "\x01"]]))
        ->toFailValidationOn('emitter.contacts.website', 'The emitter.contacts.website contains characters that XML 1.0 does not allow.');
});

it('writes a decimal element at the scale of its field', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    $xml->decimal($root, 'Price', BigDecimal::of('30000.00000'), 'lines.0.price');
    $xml->decimal($root, 'TaxPercentage', BigDecimal::of('15.500'), 'lines.0.taxes.0.taxPercentage', 3);

    expect($xml->decimal($root, 'NetTotal', null, 'lines.0.netTotal'))->toBeNull()
        ->and($xml->toXml())->toContain('<Price>30000</Price><TaxPercentage>15.5</TaxPercentage></Dfe>')
        ->and(fn (): ?DOMElement => $xml->decimal($root, 'TaxPercentage', BigDecimal::of('15.1234'), 'lines.0.taxes.0.taxPercentage', 3))
        ->toFailValidationOn('lines.0.taxes.0.taxPercentage', 'Value exceeds the allowed decimal precision.');
});

it('requires a value on its field path', function (): void {
    $xml = new XmlWriter;

    expect($xml->required('A-1', 'header.serie'))->toBe('A-1')
        ->and(fn (): mixed => $xml->required(null, 'header.serie'))
        ->toFailValidationOn('header.serie', 'The header.serie is required to write the XML document.');
});

it('writes a foreign element in its own namespace or in the default one', function (?string $namespace, string $expected): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    $xml->foreign($xml->container($root, 'ExtraFields'), 'QualquerCampo', $namespace, 'a & b', 'footer.extraFields.0');

    expect($xml->toXml())->toContain('<ExtraFields>' . $expected . '</ExtraFields>');
})->with([
    'default namespace' => [null, '<QualquerCampo>a &amp; b</QualquerCampo>'],
    'own namespace'     => ['urn:example:extra', '<QualquerCampo xmlns="urn:example:extra">a &amp; b</QualquerCampo>'],
]);

it('writes an empty foreign element when the extra field is explicitly empty', function (): void {
    $xml = new XmlWriter;
    $xml->foreign($xml->document('Dfe'), 'Flag', null, '', 'footer.extraFields.0');

    expect($xml->toXml())->toContain('<Flag></Flag>');
});

it('rejects invalid foreign text on its field path', function (): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect(fn (): DOMElement => $xml->foreign($root, 'Flag', null, "\x07", 'footer.extraFields.0'))
        ->toFailValidationOn('footer.extraFields.0.value', 'The footer.extraFields.0.value contains characters that XML 1.0 does not allow.');
});

it('rejects element and attribute names that are not XML names as definition errors', function (Closure $write): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect(fn () => $write($xml, $root))->toThrow(DefinitionException::class, 'XML element or attribute name');
})->with([
    'root'      => [fn (XmlWriter $xml): DOMElement => new XmlWriter()->document('1Dfe')],
    'element'   => [fn (XmlWriter $xml, DOMElement $root): ?DOMElement => $xml->element($root, 'Bad Name', 'x', 'x')],
    'container' => [fn (XmlWriter $xml, DOMElement $root): DOMElement => $xml->container($root, '<Lines>')],
    'attribute' => [function (XmlWriter $xml, DOMElement $root): void {
        $xml->attribute($root, 'a:b', 'x', 'x');
    }],
    'superscript' => [fn (XmlWriter $xml, DOMElement $root): DOMElement => $xml->container($root, 'a²')],
]);

it('accepts the XML 1.0 name characters the DOM accepts', function (string $name): void {
    $xml = new XmlWriter;
    $xml->container($xml->document('Dfe'), $name);
    $xml->foreign($xml->document('Dfe'), $name, null, 'v', 'footer.extraFields.0');

    expect($xml->toXml())->toContain('<' . $name . '/>')
        ->and(FiscalRules::isXmlName($name))->toBeTrue();
})->with(['accented start' => ['Ângulo'], 'middle dot' => ['a·b'], 'combining mark' => ['á'], 'undertie' => ['a‿b'], 'astral' => ["\u{10000}a"]]);

it('rejects a foreign element name the XML document cannot carry on its field path', function (string $name): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect(fn (): DOMElement => $xml->foreign($root, $name, null, 'v', 'footer.extraFields.2'))
        ->toFailValidationOn('footer.extraFields.2.name', 'The footer.extraFields.2.name must be an XML 1.0 element name.');
})->with(['empty' => [''], 'superscript' => ['a²'], 'fraction' => ['a½'], 'ordinal' => ['aª']]);

it('rejects a foreign namespace the XML document cannot declare on its field path', function (string $namespace): void {
    $xml  = new XmlWriter;
    $root = $xml->document('Dfe');

    expect(fn (): DOMElement => $xml->foreign($root, 'Flag', $namespace, 'v', 'footer.extraFields.1'))
        ->toFailValidationOn('footer.extraFields.1.namespace', 'The footer.extraFields.1.namespace must be a namespace URI that an XML document can declare.');
})->with([
    'xmlns namespace' => ['http://www.w3.org/2000/xmlns/'],
    'xml namespace'   => ['http://www.w3.org/XML/1998/namespace'],
    'markup'          => ['urn:x"y<z'],
    'braces'          => ['urn:x{y}'],
    'backtick'        => ['urn:x`y'],
    'whitespace'      => ['urn:x y'],
    'relative'        => ['fields/extra'],
]);

it('keeps the rejected name out of the definition error', function (): void {
    $xml = new XmlWriter;

    expect(fn (): DOMElement => $xml->document('secret name'))->toThrow(fn (DefinitionException $exception) => expect($exception->getMessage())
        ->not->toContain('secret')
        ->and($exception->errorCode)->toBe('definition.xml_name'));
});

it('produces identical compact bytes for identical input', function (): void {
    $write = function (): string {
        $xml  = new XmlWriter;
        $root = $xml->document('Dfe');
        $xml->element($xml->container($root, 'Invoice'), 'Serie', 'A-1', 'header.serie');

        return $xml->toXml();
    };

    expect($write())->toBe($write())
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->not->toContain('  ')
        ->not->toContain("\n<Invoice");
});
