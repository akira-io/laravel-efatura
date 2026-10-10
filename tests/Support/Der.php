<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Carbon\CarbonImmutable;

final class Der
{
    public static function tlv(int $tag, string $content): string
    {
        $length = \strlen($content);
        if ($length < 0x80) {
            return \chr($tag) . \chr($length) . $content;
        }

        $bytes = ltrim(pack('N', $length), "\0");

        return \chr($tag) . \chr(0x80 | \strlen($bytes)) . $bytes . $content;
    }

    public static function sequence(string ...$items): string
    {
        return self::tlv(0x30, implode('', $items));
    }

    public static function set(string ...$items): string
    {
        return self::tlv(0x31, implode('', $items));
    }

    public static function integerHex(string $hex): string
    {
        $digits = ltrim($hex, '0') ?: '0';
        $bytes  = (string) hex2bin(\strlen($digits) % 2 === 1 ? '0' . $digits : $digits);

        return self::tlv(0x02, (\ord($bytes[0]) & 0x80) !== 0 ? "\0" . $bytes : $bytes);
    }

    public static function oid(string $dotted): string
    {
        $arcs  = array_map(intval(...), explode('.', $dotted));
        $bytes = \chr(40 * $arcs[0] + $arcs[1]);
        foreach (\array_slice($arcs, 2) as $arc) {
            $chunk = \chr($arc & 0x7F);
            while (($arc >>= 7) > 0) {
                $chunk = \chr(0x80 | ($arc & 0x7F)) . $chunk;
            }

            $bytes .= $chunk;
        }

        return self::tlv(0x06, $bytes);
    }

    public static function null(): string
    {
        return "\x05\x00";
    }

    public static function boolean(bool $value): string
    {
        return self::tlv(0x01, $value ? "\xFF" : "\x00");
    }

    public static function bitString(string $bytes, int $unusedBits = 0): string
    {
        return self::tlv(0x03, \chr($unusedBits) . $bytes);
    }

    public static function octetString(string $bytes): string
    {
        return self::tlv(0x04, $bytes);
    }

    public static function utcTime(CarbonImmutable $moment): string
    {
        return self::tlv(0x17, $moment->utc()->format('ymdHis') . 'Z');
    }

    public static function explicit(int $number, string $content): string
    {
        return self::tlv(0xA0 | $number, $content);
    }

    /**
     * @param list<list<array{0: string, 1: string, 2?: int}>> $rdns
     */
    public static function name(array $rdns): string
    {
        return self::sequence(...array_map(
            static fn (array $rdn): string => self::set(...array_map(
                static fn (array $attribute): string => self::sequence(self::oid($attribute[0]), self::tlv($attribute[2] ?? 0x0C, $attribute[1])),
                $rdn,
            )),
            $rdns,
        ));
    }

    public static function pem(string $der, string $label = 'CERTIFICATE'): string
    {
        return '-----BEGIN ' . $label . "-----\n" . chunk_split(base64_encode($der), 64, "\n") . '-----END ' . $label . "-----\n";
    }

    public static function fromPem(string $pem): string
    {
        return (string) base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
    }
}
