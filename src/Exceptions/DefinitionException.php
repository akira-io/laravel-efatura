<?php

declare(strict_types=1);

namespace Akira\Efatura\Exceptions;

final class DefinitionException extends EfaturaException
{
    private function __construct(string $errorCode, string $message)
    {
        parent::__construct($errorCode, $message);
    }

    public static function negativeScale(int $scale): self
    {
        return new self('definition.negative_scale', \sprintf('Decimal scale must not be negative, %d given.', $scale));
    }

    public static function moneyScale(int $scale, int $maximum): self
    {
        return new self('definition.money_scale', \sprintf('Money scale must be between 0 and %d, %d given.', $maximum, $scale));
    }

    public static function roundingScale(int $scale): self
    {
        return new self('definition.rounding_scale', \sprintf('Rounded fiscal Money uses two decimal places, %d given.', $scale));
    }

    public static function numericBound(string $bound, int $scale): self
    {
        return new self('definition.numeric_bound', \sprintf('Numeric bound "%s" must be a plain decimal within scale %d.', $bound, $scale));
    }
}
