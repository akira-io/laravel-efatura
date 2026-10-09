<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use ReflectionMethod;
use ReflectionParameter;
use SensitiveParameter;

trait RedactsSensitiveParameters
{
    /**
     * @return array<array-key, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->redactedProperties();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->redactedProperties();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function redactedProperties(): array
    {
        $properties = get_object_vars($this);

        foreach (new ReflectionMethod($this, '__construct')->getParameters() as $parameter) {
            if ($this->isRedacted($parameter, $properties)) {
                $properties[$parameter->getName()] = '[redacted]';
            }
        }

        return $properties;
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    private function isRedacted(ReflectionParameter $parameter, array $properties): bool
    {
        return $parameter->getAttributes(SensitiveParameter::class) !== [] && ($properties[$parameter->getName()] ?? null) !== null;
    }
}
