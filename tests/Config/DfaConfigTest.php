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
