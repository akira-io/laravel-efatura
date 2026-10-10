<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\XmlWriter;
use Closure;
use Illuminate\Support\Str;

final class XmlFragment
{
    public static function of(Closure $write): string
    {
        $xml = new XmlWriter;
        $write($xml, $xml->document('Dfe'));

        $document = $xml->toXml();
        $open     = '<Dfe xmlns="' . Fiscal::XML_NAMESPACE . '">';

        return Str::contains($document, $open) ? Str::between($document, $open, '</Dfe>') : '';
    }
}
