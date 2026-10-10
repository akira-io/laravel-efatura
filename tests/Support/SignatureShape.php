<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\Fiscal;
use DOMDocument;
use DOMElement;

final class SignatureShape
{
    public const array ENVELOPED_TRANSFORM = [
        '   ds:Transforms',
        '    ds:Transform Algorithm=http://www.w3.org/2000/09/xmldsig#enveloped-signature',
    ];

    private const array ATTRIBUTES = ['Id', 'URI', 'Type', 'Target', 'ObjectReference', 'Algorithm'];

    /**
     * @return list<string>
     */
    public static function of(string $xml): array
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        $signature = SignatureVerifier::xpath($document)->query('//ds:Signature')?->item(0);
        $root      = $signature?->parentNode?->nodeName === Fiscal::DETACHED_SIGNATURE_ROOT ? $signature->nextSibling : $signature?->parentNode;
        while ($root !== null && ! $root instanceof DOMElement) {
            $root = $root->nextSibling;
        }

        return $signature instanceof DOMElement ? self::walk($signature, '#' . $root?->getAttribute('Id'), 0) : [];
    }

    /**
     * @param  list<string> $shape
     * @return list<string>
     */
    public static function without(array $shape, string $element): array
    {
        $kept  = [];
        $depth = null;
        foreach ($shape as $line) {
            $indent = \strlen($line) - \strlen(ltrim($line));
            if ($depth !== null && $indent > $depth) {
                continue;
            }

            $depth = null;
            if (ltrim($line) === $element) {
                $depth = $indent;

                continue;
            }

            $kept[] = $line;
        }

        return $kept;
    }

    /**
     * @return list<string>
     */
    private static function walk(DOMElement $element, string $rootReference, int $depth): array
    {
        $attributes = [];
        foreach (self::ATTRIBUTES as $name) {
            if ($element->hasAttribute($name)) {
                $value        = $element->getAttribute($name);
                $attributes[] = $name . '=' . ($value === $rootReference ? '#ROOT' : $value);
            }
        }

        $lines = [str_repeat(' ', $depth) . trim($element->nodeName . ' ' . implode(' ', $attributes))];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                array_push($lines, ...self::walk($child, $rootReference, $depth + 1));
            }
        }

        return $lines;
    }
}
