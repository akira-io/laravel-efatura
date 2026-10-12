<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\Guides;

const COMPLIANCE_MATRIX = '18-compliance-manual-v11.md';

/**
 * @return list<string>
 */
function matrixReferences(string $pattern): array
{
    preg_match_all($pattern, Guides::read(COMPLIANCE_MATRIX), $matches);

    return array_values(array_unique($matches[1]));
}

it('points every test of the compliance matrix at a file that exists', function (): void {
    $paths   = matrixReferences('/`(tests\/[\w\/.-]+\.php)`/');
    $missing = array_values(array_filter($paths, static fn (string $path): bool => ! is_file(dirname(__DIR__, 2) . '/' . $path)));

    expect($paths)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

it('names only package classes and methods that exist in the compliance matrix', function (): void {
    $symbols = matrixReferences('/`((?:[A-Z][A-Za-z\d]*\\\)+[A-Z][A-Za-z\d]*(?:::\w+\(\))?)`/');
    $missing = array_values(array_filter($symbols, static function (string $symbol): bool {
        [$name, $method] = explode('::', $symbol . '::');
        $class           = collect([$name, 'Akira\Efatura\\' . $name])
            ->first(static fn (string $candidate): bool => class_exists($candidate) || interface_exists($candidate) || enum_exists($candidate));

        return $name !== 'Akira\Efatura' && ($class === null || ($method !== '' && ! method_exists($class, rtrim($method, '()'))));
    }));

    expect($symbols)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});
