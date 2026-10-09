<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Tests\Support\DocumentFixtures;

beforeEach(function (): void {
    $this->document = fn (array $field): ElectronicInvoiceData => ElectronicInvoiceData::from(
        DocumentFixtures::payload(['footer' => ['extraFields' => [['name' => 'CustomerHint', 'value' => 'Ready'], $field]]]),
    );
});

it('rejects an extra field name outside the XML 1.0 name characters on its document path', function (string $name): void {
    expect(fn (): ElectronicInvoiceData => ($this->document)(['name' => $name, 'value' => 'v']))
        ->toFailValidationOn('footer.extraFields.1.name', 'The footer.extra fields.1.name field format is invalid.');
})->with(['superscript' => ['a²'], 'fraction' => ['a½'], 'ordinal' => ['aª'], 'leading digit' => ['1a'], 'leading dot' => ['.a']]);

it('rejects an extra field namespace the XML document cannot declare on its document path', function (string $namespace, string $message): void {
    expect(fn (): ElectronicInvoiceData => ($this->document)(['name' => 'Route', 'value' => 'v', 'namespace' => $namespace]))
        ->toFailValidationOn('footer.extraFields.1.namespace', $message);
})->with([
    'xmlns namespace' => ['http://www.w3.org/2000/xmlns/', 'The selected footer.extra fields.1.namespace is invalid.'],
    'xml namespace'   => ['http://www.w3.org/XML/1998/namespace', 'The selected footer.extra fields.1.namespace is invalid.'],
    'markup'          => ['urn:x"y<z', 'The footer.extra fields.1.namespace field format is invalid.'],
    'braces'          => ['urn:x{y}', 'The footer.extra fields.1.namespace field format is invalid.'],
    'pipe'            => ['urn:x|y', 'The footer.extra fields.1.namespace field format is invalid.'],
    'backslash'       => ['urn:x\y', 'The footer.extra fields.1.namespace field format is invalid.'],
    'caret'           => ['urn:x^y', 'The footer.extra fields.1.namespace field format is invalid.'],
    'backtick'        => ['urn:x`y', 'The footer.extra fields.1.namespace field format is invalid.'],
]);

it('accepts the XML 1.0 name characters and an ordinary namespace', function (string $name): void {
    $field = ExtraFieldData::from(['name' => $name, 'value' => 'v', 'namespace' => 'https://example.cv/fields?v=1#extra']);

    expect($field->name)->toBe($name);
})->with(['accented start' => ['Ângulo'], 'middle dot' => ['a·b'], 'combining mark' => ['á'], 'astral' => ["\u{10000}a"]]);
