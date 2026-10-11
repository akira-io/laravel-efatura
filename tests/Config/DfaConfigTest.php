<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\DfaConfig;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\ConfigFixtures;

it('points the DFA QR code at the official DFE viewer by default', function (): void {
    $config = ConfigFixtures::load()->dfa;

    expect($config)->toEqual(new DfaConfig)
        ->and($config->baseUrl)->toBe('https://pe.efatura.cv/dfe/view');
});

it('reads the DFA base url without its trailing slashes', function (string $configured, string $expected): void {
    expect(ConfigFixtures::load(['dfa' => ['base_url' => $configured]])->dfa->baseUrl)->toBe($expected);
})->with([
    'one slash'    => ['https://pe.efatura.cv/dfe/view/', 'https://pe.efatura.cv/dfe/view'],
    'many slashes' => ['https://viewer.example.cv/dfe///', 'https://viewer.example.cv/dfe'],
    'trimmed'      => ['  https://viewer.example.cv  ', 'https://viewer.example.cv'],
]);

it('refuses a DFA base url that is not plain https', function (mixed $configured, string $errorCode): void {
    expect(fn (): DfaConfig => ConfigFixtures::load(['dfa' => ['base_url' => $configured]])->dfa)
        ->toThrow(function (ConfigurationException $exception) use ($errorCode): void {
            expect($exception->errorCode)->toBe($errorCode)
                ->and($exception->field)->toBe('efatura.dfa.base_url');
        });
})->with([
    'http'        => ['http://pe.efatura.cv/dfe/view', 'configuration.unsafe_url'],
    'credentials' => ['https://user:secret@pe.efatura.cv/dfe/view', 'configuration.unsafe_url'],
    'query'       => ['https://pe.efatura.cv/dfe/view?iud=1', 'configuration.unsafe_url'],
    'fragment'    => ['https://pe.efatura.cv/dfe/view#qr', 'configuration.unsafe_url'],
    'not a url'   => ['pe.efatura.cv/dfe/view', 'configuration.unsafe_url'],
    'empty'       => [' ', 'configuration.empty_string'],
    'not text'    => [42, 'configuration.invalid_type'],
    'null'        => [null, 'configuration.invalid_type'],
]);

it('refuses a DFA section that is not a map', function (): void {
    expect(fn (): DfaConfig => ConfigFixtures::load(['dfa' => 'https://pe.efatura.cv'])->dfa)
        ->toThrow(ConfigurationException::class, 'configuration.invalid_type: efatura.dfa');
});

it('leaves the dompdf cache directory to the system temp directory unless one is configured', function (mixed $configured, ?string $expected): void {
    expect(ConfigFixtures::load(['dfa' => ['cache_path' => $configured]])->dfa->cachePath)->toBe($expected);
})->with([
    'absent'         => [null, null],
    'absolute'       => ['/var/cache/efatura-dompdf', '/var/cache/efatura-dompdf'],
    'trailing slash' => ['/var/cache/efatura-dompdf/', '/var/cache/efatura-dompdf'],
]);

it('refuses a dompdf cache directory that is not a plain absolute path', function (mixed $configured, string $errorCode): void {
    expect(fn (): DfaConfig => ConfigFixtures::load(['dfa' => ['cache_path' => $configured]])->dfa)
        ->toThrow(function (ConfigurationException $exception) use ($errorCode): void {
            expect($exception->errorCode)->toBe($errorCode)
                ->and($exception->field)->toBe('efatura.dfa.cache_path');
        });
})->with([
    'relative'      => ['storage/dompdf', 'configuration.unsafe_path'],
    'root'          => ['/', 'configuration.unsafe_path'],
    'parent'        => ['/var/cache/../../etc', 'configuration.unsafe_path'],
    'current'       => ['/var/./cache', 'configuration.unsafe_path'],
    'empty segment' => ['/var//cache', 'configuration.unsafe_path'],
    'control'       => ["/var/ca\x01che", 'configuration.unsafe_path'],
    'backslash'     => ['/var\cache', 'configuration.unsafe_path'],
    'empty'         => [' ', 'configuration.empty_string'],
    'not text'      => [42, 'configuration.invalid_type'],
]);
