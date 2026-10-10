<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

final class DistinguishedName
{
    private const array DESCRIPTORS = [
        '2.5.4.3'                    => 'CN',
        '2.5.4.7'                    => 'L',
        '2.5.4.8'                    => 'ST',
        '2.5.4.10'                   => 'O',
        '2.5.4.11'                   => 'OU',
        '2.5.4.6'                    => 'C',
        '2.5.4.9'                    => 'STREET',
        '0.9.2342.19200300.100.1.25' => 'DC',
        '0.9.2342.19200300.100.1.1'  => 'UID',
    ];

    private const array STRING_ENCODINGS = [
        0x0C => 'UTF-8',
        0x12 => 'UTF-8',
        0x13 => 'UTF-8',
        0x16 => 'UTF-8',
        0x14 => 'ISO-8859-1',
        0x1C => 'UTF-32BE',
        0x1E => 'UTF-16BE',
    ];

    private const int VERSION_TAG = 0xA0;

    public static function issuerOf(string $certificateDer): string
    {
        $certificate = self::elements($certificateDer)[0]['content'] ?? '';
        $fields      = self::elements(self::elements($certificate)[0]['content'] ?? '');
        $offset      = ($fields[0]['tag'] ?? null) === self::VERSION_TAG ? 1 : 0;

        return self::fromName($fields[$offset + 2]['content'] ?? '');
    }

    public static function fromName(string $rdnSequence): string
    {
        $rdns = array_map(
            static fn (array $set): string => implode('+', array_map(self::attribute(...), self::elements($set['content']))),
            self::elements($rdnSequence),
        );

        return implode(',', array_reverse($rdns));
    }

    public static function escape(string $value): string
    {
        $escaped = str_replace("\0", '\00', (string) preg_replace('/[,+"\\\<>;]/', '\\\$0', $value));

        return (string) preg_replace('/\A[ #]| \z/', '\\\$0', $escaped);
    }

    /**
     * @param array{tag: int, content: string, encoded: string} $sequence
     */
    private static function attribute(array $sequence): string
    {
        [$type, $value] = self::elements($sequence['content']) + [1 => ['tag' => 0, 'content' => '', 'encoded' => '']];
        $oid            = self::oid($type['content']);
        $descriptor     = self::DESCRIPTORS[$oid] ?? null;
        $encoding       = self::STRING_ENCODINGS[$value['tag']] ?? null;

        if ($descriptor === null || $encoding === null) {
            return ($descriptor ?? $oid) . '=#' . bin2hex($value['encoded']);
        }

        return $descriptor . '=' . self::escape(mb_convert_encoding($value['content'], 'UTF-8', $encoding));
    }

    private static function oid(string $bytes): string
    {
        $arcs  = [];
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            $value = ($value << 7) | (\ord($byte[0]) & 0x7F);
            if ((\ord($byte[0]) & 0x80) === 0) {
                $arcs[] = $value;
                $value  = 0;
            }
        }

        $first = array_shift($arcs) ?? 0;
        $root  = min(intdiv($first, 40), 2);

        return implode('.', [$root, $first - 40 * $root, ...$arcs]);
    }

    /**
     * @return list<array{tag: int, content: string, encoded: string}>
     */
    private static function elements(string $der): array
    {
        $elements = [];
        $offset   = 0;
        while ($offset < \strlen($der)) {
            $length = \ord(($der[$offset + 1] ?? "\0")[0]);
            $header = 2;
            if ($length > 0x7F) {
                $header += $length & 0x7F;
                $length = (int) hexdec(bin2hex(substr($der, $offset + 2, $length & 0x7F)));
            }

            $elements[] = [
                'tag'     => \ord($der[$offset]),
                'content' => substr($der, $offset + $header, $length),
                'encoded' => substr($der, $offset, $header + $length),
            ];
            $offset += $header + $length;
        }

        return $elements;
    }
}
