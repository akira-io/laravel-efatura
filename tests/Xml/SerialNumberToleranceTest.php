<?php

declare(strict_types=1);

use Akira\Efatura\Xml\SerialNumberTolerance;

function libxmlError(string $message, int $code = 1824): LibXMLError
{
    $error          = new LibXMLError;
    $error->level   = LIBXML_ERR_ERROR;
    $error->code    = $code;
    $error->line    = 1;
    $error->column  = 0;
    $error->message = $message . "\n";

    return $error;
}

function serialNumberError(string $value): LibXMLError
{
    return libxmlError("Element \x27{http://www.w3.org/2000/09/xmldsig#}X509SerialNumber\x27: \x27" . $value . "\x27 is not a valid value of the atomic type \x27xs:integer\x27.");
}

it('drops the integer error an older libxml reports on a long decimal serial number', function (string $value): void {
    expect(SerialNumberTolerance::significant([serialNumberError($value)]))->toBe([]);
})->with([
    'twenty octets'    => ['730750818665451459101842416358141509827966271487'],
    'forty digits'     => [str_repeat('9', 40)],
    'with a plus sign' => ['+18446744073709551616'],
]);

it('keeps every other violation', function (LibXMLError $error): void {
    expect(SerialNumberTolerance::significant([$error]))->toBe([$error]);
})->with([
    'a serial number that is not decimal' => fn (): LibXMLError => serialNumberError('1A2B'),
    'an empty serial number'              => fn (): LibXMLError => serialNumberError(''),
    'a decimal fraction'                  => fn (): LibXMLError => serialNumberError('12.5'),
    'another element with digits'         => fn (): LibXMLError => libxmlError(
        "Element \x27{urn:cv:efatura:xsd:v1.0}DocumentNumber\x27: \x27123456789012345678901234\x27 is not a valid value of the atomic type \x27xs:integer\x27.",
    ),
    'a serial number in another namespace' => fn (): LibXMLError => libxmlError(
        "Element \x27{urn:forged}X509SerialNumber\x27: \x27730750818665451459101842416358141509827966271487\x27 is not a valid value of the atomic type \x27xs:integer\x27.",
    ),
    'another message on the serial number' => fn (): LibXMLError => libxmlError(
        "Element \x27{http://www.w3.org/2000/09/xmldsig#}X509SerialNumber\x27: This element is not expected.",
        1871,
    ),
]);

it('keeps the order of the violations it does not drop', function (): void {
    $first  = libxmlError("Element \x27{urn:cv:efatura:xsd:v1.0}Serie\x27: [facet \x27pattern\x27] The value \x27x\x27 is not accepted.");
    $second = libxmlError("Element \x27{urn:cv:efatura:xsd:v1.0}Note\x27: This element is not expected.", 1871);

    expect(SerialNumberTolerance::significant([$first, serialNumberError(str_repeat('7', 30)), $second]))->toBe([$first, $second]);
});
