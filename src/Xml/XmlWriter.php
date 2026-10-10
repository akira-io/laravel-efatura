<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use DOMDocument;
use DOMElement;
use DOMException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class XmlWriter
{
    private const string XML_TEXT = '/\A[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*\z/u';

    private DOMDocument $document;

    public function __construct()
    {
        $this->document = new DOMDocument('1.0', 'UTF-8');
    }

    public function document(string $root): DOMElement
    {
        return $this->document->appendChild($this->create(Fiscal::XML_NAMESPACE, $root));
    }

    public function container(DOMElement $parent, string $name): DOMElement
    {
        return $parent->appendChild($this->create(Fiscal::XML_NAMESPACE, $name));
    }

    public function element(DOMElement $parent, string $name, ?string $value, string $path): ?DOMElement
    {
        return $value === null ? null : $this->requiredElement($parent, $name, $value, $path);
    }

    public function requiredElement(DOMElement $parent, string $name, ?string $value, string $path): DOMElement
    {
        if ($value === null || $value === '') {
            throw self::missing($path);
        }

        return $parent->appendChild($this->withText($this->create(Fiscal::XML_NAMESPACE, $name), $value, $path));
    }

    public function decimal(DOMElement $parent, string $name, BigDecimal|Money|null $value, string $path, int $scale = Fiscal::AMOUNT_SCALE): ?DOMElement
    {
        return $this->element($parent, $name, XmlValue::decimal($value, $path, $scale), $path);
    }

    /**
     * @param array<string, array{string, ?string}> $fields
     */
    public function elements(DOMElement $parent, string $path, array $fields): void
    {
        foreach ($fields as $name => [$property, $value]) {
            $this->element($parent, $name, $value, $path . '.' . $property);
        }
    }

    public function attribute(DOMElement $element, string $name, ?string $value, string $path): void
    {
        if ($value === null) {
            return;
        }

        $element->setAttribute(self::name($name), self::checked($value, $path));
    }

    public function foreign(DOMElement $parent, string $name, ?string $namespace, string $value, string $path): DOMElement
    {
        if ($namespace !== null && ! FiscalRules::isXmlNamespace($namespace)) {
            throw self::invalid($path . '.namespace', 'xml_namespace_invalid');
        }

        try {
            $element = $this->document->createElementNS($namespace ?? Fiscal::XML_NAMESPACE, $name);
        } catch (DOMException) {
            throw self::invalid($path . '.name', 'xml_name_invalid');
        }

        return $parent->appendChild($this->withText($element, $value, $path . '.value'));
    }

    /**
     * @template TValue
     *
     * @param  TValue|null $value
     * @return TValue
     */
    public function required(mixed $value, string $path): mixed
    {
        return $value ?? throw self::missing($path);
    }

    public function toXml(): string
    {
        return (string) $this->document->saveXML();
    }

    private function withText(DOMElement $element, string $value, string $path): DOMElement
    {
        $element->appendChild($this->document->createTextNode(self::checked($value, $path)));

        return $element;
    }

    private function create(string $namespace, string $name): DOMElement
    {
        return $this->document->createElementNS($namespace, self::name($name));
    }

    private static function name(string $name): string
    {
        return FiscalRules::isXmlName($name) ? $name : throw DefinitionException::xmlName($name);
    }

    private static function checked(string $value, string $path): string
    {
        if (! mb_check_encoding($value, 'UTF-8') || ! Str::isMatch(self::XML_TEXT, $value)) {
            throw self::invalid($path, 'xml_text_invalid');
        }

        return $value;
    }

    private static function missing(string $path): ValidationException
    {
        return self::invalid($path, 'xml_required');
    }

    private static function invalid(string $path, string $message): ValidationException
    {
        return ValidationException::withMessages([$path => __('efatura::efatura.validation.' . $message, ['attribute' => $path])]);
    }
}
