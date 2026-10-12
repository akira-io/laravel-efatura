<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Support\Fiscal;
use DOMElement;

final readonly class SignatureStructure
{
    private const array NAMESPACES = ['ds' => Fiscal::XMLDSIG_NAMESPACE, 'xades' => Fiscal::XADES_NAMESPACE];

    /**
     * @param list<DOMElement> $references
     */
    private function __construct(
        public DOMElement $signedInfo,
        public array $references,
        public DOMElement $signatureValue,
        public DOMElement $certificate,
        public DOMElement $signedProperties,
    ) {}

    public static function of(DOMElement $signature): ?self
    {
        $parts = self::children($signature);
        if (! self::named($parts, ['ds:SignedInfo', 'ds:SignatureValue', 'ds:KeyInfo', 'ds:Object'])) {
            return null;
        }

        $signedInfo  = $parts[0];
        $methods     = \array_slice(self::children($signedInfo), 0, 2);
        $references  = \array_slice(self::children($signedInfo), 2);
        $certificate = self::path($parts[2], ['ds:X509Data', 'ds:X509Certificate']);
        $properties  = self::path($parts[3], ['xades:QualifyingProperties', 'xades:SignedProperties']);
        $exact       = self::named($methods, ['ds:CanonicalizationMethod', 'ds:SignatureMethod'])
            && $references !== []
            && collect($references)->every(self::reference(...))
            && collect([...$methods, $parts[1]])->every(self::leaf(...));

        return $exact && $certificate instanceof DOMElement && self::leaf($certificate) && $properties instanceof DOMElement
            ? new self($signedInfo, $references, $parts[1], $certificate, $properties)
            : null;
    }

    private static function reference(DOMElement $reference): bool
    {
        $children   = self::children($reference);
        $transforms = $children !== [] && self::is($children[0], 'ds:Transforms') ? array_shift($children) : null;

        return self::named($children, ['ds:DigestMethod', 'ds:DigestValue'])
            && collect($children)->every(self::leaf(...))
            && ($transforms === null || self::transforms($transforms));
    }

    private static function transforms(DOMElement $transforms): bool
    {
        $children = self::children($transforms);

        return $children !== []
            && collect($children)->every(static fn (DOMElement $transform): bool => self::is($transform, 'ds:Transform') && self::leaf($transform));
    }

    /**
     * @param list<string> $names
     */
    private static function path(DOMElement $parent, array $names): ?DOMElement
    {
        foreach ($names as $name) {
            $children = self::children($parent);
            if (! self::named($children, [$name])) {
                return null;
            }

            $parent = $children[0];
        }

        return $parent;
    }

    /**
     * @param list<DOMElement> $elements
     * @param list<string>     $names
     */
    private static function named(array $elements, array $names): bool
    {
        return \count($elements) === \count($names)
            && collect($elements)->every(static fn (DOMElement $element, int $index): bool => self::is($element, $names[$index]));
    }

    private static function is(DOMElement $element, string $name): bool
    {
        [$prefix, $localName] = explode(':', $name);

        return $element->namespaceURI === self::NAMESPACES[$prefix] && $element->localName === $localName;
    }

    private static function leaf(DOMElement $element): bool
    {
        return ! $element->firstElementChild instanceof DOMElement;
    }

    /**
     * @return list<DOMElement>
     */
    private static function children(DOMElement $parent): array
    {
        $children = [];
        for ($child = $parent->firstElementChild; $child instanceof DOMElement; $child = $child->nextElementSibling) {
            $children[] = $child;
        }

        return $children;
    }
}
