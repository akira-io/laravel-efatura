<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Luhn;

it('computes the check digit of the official vectors', function (string $iudWithoutCheckDigit, int $checkDigit): void {
    expect(Luhn::checkDigit(substr($iudWithoutCheckDigit, 2)))->toBe($checkDigit);
})->with([
    'manual example'             => ['CV320121412345678900001011234567891234567890', 8],
    'official enveloped invoice' => ['CV120052012345678900011234567890111234567890', 4],
    'node-efatura test vector'   => ['CV326020810020030000123010000000011234567890', 9],
]);

it('accepts the official identifiers with their check digit', function (string $iud): void {
    expect(Luhn::passes(substr($iud, 2)))->toBeTrue();
})->with([
    'official enveloped invoice' => ['CV1200520123456789000112345678901112345678904'],
    'node-efatura test vector'   => ['CV3260208100200300001230100000000112345678909'],
]);

it('rejects every wrong check digit', function (int $checkDigit): void {
    expect(Luhn::passes('326020810020030000123010000000011234567890' . $checkDigit))->toBeFalse();
})->with([0, 1, 2, 3, 4, 5, 6, 7, 8]);

it('doubles the rightmost payload digit and sums the digits of each product', function (string $digits, int $checkDigit): void {
    expect(Luhn::checkDigit($digits))->toBe($checkDigit);
})->with([
    'zero'                  => ['0', 0],
    'doubled nine'          => ['9', 1],
    'undoubled second nine' => ['99', 2],
    'textbook example'      => ['7992739871', 3],
]);

it('refuses a payload that is not made of digits without revealing it', function (string $payload, int $length): void {
    expect(fn (): int => Luhn::checkDigit($payload))
        ->toThrow(DefinitionException::class, sprintf('A Luhn payload must contain only ASCII digits, %d characters given.', $length));
})->with([
    'letter'        => ['12a', 3],
    'empty'         => ['', 0],
    'unicode digit' => ['١٢', 2],
]);

it('refuses to check an identifier that is not made of digits', function (): void {
    expect(fn (): bool => Luhn::passes('12a4'))
        ->toThrow(DefinitionException::class, 'A Luhn payload must contain only ASCII digits, 4 characters given.');
});

it('rejects an identifier too short to carry a check digit', function (string $digits): void {
    expect(Luhn::passes($digits))->toBeFalse();
})->with(['empty' => [''], 'one digit' => ['0'], 'another digit' => ['9']]);
