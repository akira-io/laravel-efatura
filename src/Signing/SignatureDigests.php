<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Brick\Math\BigInteger;
use DOMNode;

final class SignatureDigests
{
    public static function of(string $bytes): string
    {
        return base64_encode(hash('sha256', $bytes, true));
    }

    public static function canonical(DOMNode $node): string
    {
        return (string) $node->C14N(false, false);
    }

    public static function node(DOMNode $node): string
    {
        return self::of(self::canonical($node));
    }

    public static function serialNumber(string $hexadecimal): string
    {
        return BigInteger::fromBase($hexadecimal, 16)->toBase(10);
    }
}
