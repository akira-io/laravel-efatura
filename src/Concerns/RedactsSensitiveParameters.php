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
        $redacted   = [];

        foreach (new ReflectionMethod($this, '__construct')->getParameters() as $parameter) {
            $redacted[$parameter->getName()] = $this->isRedacted($parameter, $properties) ? '[redacted]' : $properties[$parameter->getName()];
        }

        return $redacted;
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    private function isRedacted(ReflectionParameter $parameter, array $properties): bool
    {
        return $parameter->getAttributes(SensitiveParameter::class) !== [] && ($properties[$parameter->getName()] ?? null) !== null;
    }
}
