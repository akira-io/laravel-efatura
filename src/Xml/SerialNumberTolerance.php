<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use LibXMLError;

final class SerialNumberTolerance
{
    private const string LONG_SERIAL_NUMBER = '/\\AElement \'\\{http:\\/\\/www\\.w3\\.org\\/2000\\/09\\/xmldsig#\\}X509SerialNumber\': \'[+-]?\\d+\''
        . ' is not a valid value of the atomic type \'xs:integer\'\\.\\s*\\z/';

    /**
     * @param  list<LibXMLError> $errors
     * @return list<LibXMLError>
     */
    public static function significant(array $errors): array
    {
        return array_values(array_filter($errors, static fn (LibXMLError $error): bool => preg_match(self::LONG_SERIAL_NUMBER, $error->message) !== 1));
    }
}
