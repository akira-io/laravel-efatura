<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

interface DocumentXmlSerializer
{
    public function append(XmlWriter $xml, DOMElement $dfe, DocumentData $document): void;
}
