<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

final class OpenSslErrors
{
    private const int PKCS12_MAC_VERIFY_FAILURE = 0x11800071;

    private const int UNSUPPORTED_REASON = 0x8010C;

    private const int REASON_MASK = 0x7FFFFF;

    /**
     * @return list<int>
     */
    public static function drain(): array
    {
        $codes = [];
        while (($error = openssl_error_string()) !== false) {
            $codes[] = preg_match('/\Aerror:([0-9A-Fa-f]{8}):/', $error, $match) === 1 ? (int) hexdec($match[1]) : 0;
        }

        return $codes;
    }

    /**
     * @param list<int> $codes
     */
    public static function pkcs12Failure(array $codes): string
    {
        if (\in_array(self::PKCS12_MAC_VERIFY_FAILURE, $codes, true)) {
            return 'certificate.passphrase_invalid';
        }

        return collect($codes)->contains(static fn (int $code): bool => ($code & self::REASON_MASK) === self::UNSUPPORTED_REASON)
            ? 'certificate.pkcs12_unsupported'
            : 'certificate.invalid';
    }
}
