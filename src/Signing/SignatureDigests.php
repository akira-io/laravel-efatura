<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Brick\Math\BigInteger;
use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMXPath;

use const ARRAY_FILTER_USE_BOTH;
use const ENT_QUOTES;
use const ENT_XML1;
use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

final class SignatureDigests
{
    public static function of(string $bytes): string
    {
        return base64_encode(hash('sha256', $bytes, true));
    }

    public static function canonical(DOMNode $node): string
    {
        if (! $node instanceof DOMElement || ! $node->ownerDocument instanceof DOMDocument) {
            return (string) $node->C14N(false, false);
        }

        $tag      = '<' . $node->nodeName;
        $inherits = '';
        foreach (self::inherited($node) as $name => $value) {
            $inherits .= ' ' . $name . '="' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"';
        }

        $standalone = new DOMDocument;
        $standalone->loadXML(
            '<?xml version="1.0" encoding="UTF-8"?>' . $tag . $inherits . substr((string) $node->ownerDocument->saveXML($node), \strlen($tag)),
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        return (string) $standalone->C14N(false, false);
    }

    public static function node(DOMNode $node): string
    {
        return self::of(self::canonical($node));
    }

    public static function serialNumber(string $hexadecimal): string
    {
        return BigInteger::fromBase($hexadecimal, 16)->toBase(10);
    }

    /**
     * @return array<string, string>
     */
    private static function inherited(DOMElement $element): array
    {
        $xpath     = new DOMXPath($element->ownerDocument ?? new DOMDocument);
        $inherited = [];
        foreach ($xpath->query('namespace::*[name() != "xml"]', $element) ?: [] as $namespace) {
            if ($namespace instanceof DOMNameSpaceNode) {
                $inherited[$namespace->prefix === '' ? 'xmlns' : 'xmlns:' . $namespace->prefix] = (string) $namespace->namespaceURI;
            }
        }

        foreach ($xpath->query('ancestor::*/@xml:*', $element) ?: [] as $attribute) {
            $inherited[$attribute->nodeName] = (string) $attribute->nodeValue;
        }

        return array_filter($inherited, static fn (string $value, string $name): bool => ! $element->hasAttribute($name), ARRAY_FILTER_USE_BOTH);
    }
}
