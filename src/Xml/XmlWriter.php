<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class XmlWriter
{
    private const string NAME = '/\A[\p{L}_][\p{L}\p{N}_.-]*\z/u';

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

        return $this->text($parent, Fiscal::XML_NAMESPACE, $name, $value, $path);
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
        return $this->text($parent, $namespace ?? Fiscal::XML_NAMESPACE, $name, $value, $path);
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

    private function text(DOMElement $parent, string $namespace, string $name, string $value, string $path): DOMElement
    {
        $element = $this->create($namespace, $name);
        $element->appendChild($this->document->createTextNode(self::checked($value, $path)));

        return $parent->appendChild($element);
    }

    private function create(string $namespace, string $name): DOMElement
    {
        return $this->document->createElementNS($namespace, self::name($name));
    }

    private static function name(string $name): string
    {
        return Str::isMatch(self::NAME, $name) ? $name : throw DefinitionException::xmlName($name);
    }

    private static function checked(string $value, string $path): string
    {
        if (! mb_check_encoding($value, 'UTF-8') || ! Str::isMatch(self::XML_TEXT, $value)) {
            throw ValidationException::withMessages([$path => __('efatura::efatura.validation.xml_text_invalid', ['attribute' => $path])]);
        }

        return $value;
    }

    private static function missing(string $path): ValidationException
    {
        return ValidationException::withMessages([$path => __('efatura::efatura.validation.xml_required', ['attribute' => $path])]);
    }
}
