<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

use Akira\Efatura\Xml\SchemaViolation;

final class SchemaValidationException extends EfaturaException
{
    /**
     * @param list<SchemaViolation> $violations
     */
    private function __construct(string $errorCode, public readonly array $violations = [])
    {
        parent::__construct($errorCode, $errorCode, context: ['violations' => \count($violations)]);
    }

    /**
     * @param list<SchemaViolation> $violations
     */
    public static function schemaInvalid(array $violations): self
    {
        return new self('xml.schema_invalid', $violations);
    }

    /**
     * @param list<SchemaViolation> $violations
     */
    public static function malformed(array $violations): self
    {
        return new self('xml.malformed', $violations);
    }

    public static function doctypeForbidden(): self
    {
        return new self('xml.doctype_forbidden');
    }

    public static function externalResource(): self
    {
        return new self('xml.external_resource');
    }
}
