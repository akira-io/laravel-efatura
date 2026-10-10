<?php

declare(strict_types=1);

use Akira\Efatura\Signing\DistinguishedName;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\Der;

it('writes the issuer of a certificate in rfc 4514 order', function (): void {
    expect(DistinguishedName::issuerOf(C::signer()->der()))->toBe(C::CA_NAME)
        ->and(DistinguishedName::issuerOf(C::ca()->der()))->toBe(C::CA_NAME);
});

it('reads the issuer of a version one certificate', function (): void {
    $tbs = Der::sequence(Der::integerHex('01'), Der::sequence(Der::oid('1.2.840.113549.1.1.11'), Der::null()), Der::name([[['2.5.4.3', 'Legacy']]]));

    expect(DistinguishedName::issuerOf(Der::sequence($tbs)))->toBe('CN=Legacy');
});

it('reverses the relative distinguished names', function (): void {
    $name = Der::name([[['2.5.4.6', 'CV', 0x13]], [['2.5.4.10', 'Org']], [['2.5.4.11', 'Unit']], [['2.5.4.3', 'Name']]]);

    expect(DistinguishedName::fromName(substr($name, 2)))->toBe('CN=Name,OU=Unit,O=Org,C=CV');
});

it('names every rfc 4514 attribute type by its descriptor', function (): void {
    $name = Der::name([
        [['0.9.2342.19200300.100.1.25', 'cv']],
        [['2.5.4.8', 'Santiago'], ['2.5.4.7', 'Praia']],
        [['2.5.4.9', 'Rua 1']],
        [['0.9.2342.19200300.100.1.1', 'signer']],
    ]);

    expect(DistinguishedName::fromName(substr($name, 2)))->toBe('UID=signer,STREET=Rua 1,ST=Santiago+L=Praia,DC=cv');
});

it('writes other attribute types and non string values as dotted oids with their ber bytes', function (): void {
    $name = Der::name([[['2.5.4.97', 'VATCV-1']], [['1.2.840.113549.1.9.1', 'a@b.cv', 0x16]], [['2.5.4.3', "\x01", 0x04]]]);

    expect(DistinguishedName::fromName(substr($name, 2)))->toBe('CN=#040101,1.2.840.113549.1.9.1=#16066140622e6376,2.5.4.97=#0c0756415443562d31');
});

it('decodes the string encodings a certificate may use', function (int $tag, string $bytes, string $expected): void {
    $name = Der::name([[['2.5.4.3', $bytes, $tag]]]);

    expect(DistinguishedName::fromName(substr($name, 2)))->toBe('CN=' . $expected);
})->with([
    'utf8'      => [0x0C, 'São Vicente', 'São Vicente'],
    'printable' => [0x13, 'Praia', 'Praia'],
    'ia5'       => [0x16, 'praia', 'praia'],
    'numeric'   => [0x12, '7600', '7600'],
    'teletex'   => [0x14, "S\xE3o", 'São'],
    'bmp'       => [0x1E, "\x00S\x00\xE3\x00o", 'São'],
    'universal' => [0x1C, "\x00\x00\x00S\x00\x00\x00\xE3\x00\x00\x00o", 'São'],
]);

it('escapes the characters rfc 4514 reserves', function (string $value, string $escaped): void {
    expect(DistinguishedName::escape($value))->toBe($escaped);
})->with([
    'separators'      => ['Doe, John + Co; <x>', 'Doe\, John \+ Co\; \<x\>'],
    'quote and slash' => ['"a\b"', '\"a\\\b\"'],
    'leading hash'    => ['#1', '\#1'],
    'leading space'   => [' a', '\ a'],
    'trailing space'  => ['a ', 'a\ '],
    'single space'    => [' ', '\ '],
    'nul'             => ["a\0b", 'a\00b'],
    'inner hash'      => ['a#b c', 'a#b c'],
]);
