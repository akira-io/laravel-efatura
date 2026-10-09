<?php

declare(strict_types=1);

namespace Akira\Efatura\Tools\Catalogs;

use DOMDocument;
use DOMElement;
use Illuminate\Filesystem\Filesystem;
use UnexpectedValueException;

use const LIBXML_NONET;

final readonly class XsdEnumerations
{
    private const string SCHEMA = 'http://www.w3.org/2001/XMLSchema';

    private const string COMPONENTS = 'urn:un:unece:uncefact:documentation:standard:CoreComponentsTechnicalSpecification:2';

    public function __construct(private Filesystem $files) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function records(string $path): array
    {
        $document = new DOMDocument;

        throw_unless($document->loadXML($this->files->get($path), LIBXML_NONET), UnexpectedValueException::class, 'Unreadable XSD: ' . $path);

        return collect($document->getElementsByTagNameNS(self::SCHEMA, 'enumeration'))
            ->map($this->record(...))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function record(DOMElement $enumeration): array
    {
        $name = trim($enumeration->getElementsByTagNameNS(self::COMPONENTS, 'Name')->item(0)->textContent ?? '');

        return ['code' => $enumeration->getAttribute('value'), ...($name === '' ? [] : ['name' => $name])];
    }
}
