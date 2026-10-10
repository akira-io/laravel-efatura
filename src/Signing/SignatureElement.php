<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use DOMElement;
use DOMText;

final class SignatureElement
{
    /**
     * @param array<string, string> $attributes
     */
    public static function append(DOMElement $parent, string $namespace, string $qualifiedName, ?string $text = null, array $attributes = []): DOMElement
    {
        $element = new DOMElement($qualifiedName, namespace: $namespace);
        $parent->appendChild($element);

        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }

        if ($text !== null) {
            $element->appendChild(new DOMText($text));
        }

        return $element;
    }
}
