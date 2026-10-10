<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\Fiscal;
use DOMDocument;

final readonly class XsdTypeProbe
{
    public function __construct(private string $type) {}

    public function accepts(string ...$values): bool
    {
        $document = new DOMDocument;
        $root     = $document->appendChild($document->createElementNS(Fiscal::XML_NAMESPACE, 'values'));
        foreach ($values as $value) {
            $root->appendChild($document->createElementNS(Fiscal::XML_NAMESPACE, 'value'))->textContent = $value;
        }

        $internalErrors = libxml_use_internal_errors(true);

        try {
            return $document->schemaValidateSource($this->schema());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
    }

    private function schema(): string
    {
        $types = SchemaFixtures::resources() . '/' . SchemaFixtures::XSD . 'common/CV_EFatura_Types_v1.0.xsd';

        return \sprintf(
            '<x:schema xmlns:x="http://www.w3.org/2001/XMLSchema" xmlns:ef="%1$s" targetNamespace="%1$s" elementFormDefault="qualified">'
            . '<x:include schemaLocation="%2$s"/><x:element name="values"><x:complexType><x:sequence>'
            . '<x:element name="value" type="ef:%3$s" maxOccurs="unbounded"/></x:sequence></x:complexType></x:element></x:schema>',
            Fiscal::XML_NAMESPACE,
            $types,
            $this->type,
        );
    }
}
