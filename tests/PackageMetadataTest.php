<?php

declare(strict_types=1);

function composerMetadata(): array
{
    return json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('declares the runtime platform and imported Laravel components', function (): void {
    $requirements = composerMetadata()['require'];

    expect($requirements)->toMatchArray([
        'php'                   => '^8.5',
        'ext-dom'               => '*',
        'ext-libxml'            => '*',
        'ext-openssl'           => '*',
        'ext-zip'               => '*',
        'illuminate/config'     => '^13.0',
        'illuminate/console'    => '^13.0',
        'illuminate/contracts'  => '^13.0',
        'illuminate/filesystem' => '^13.0',
        'illuminate/support'    => '^13.0',
        'illuminate/validation' => '^13.0',
    ]);
});

it('retains the package runtime and development tools', function (): void {
    $composer = composerMetadata();

    expect($composer['require'])->toMatchArray([
        'brick/money'                  => '^0.11.0',
        'spatie/laravel-data'          => '^4.19',
        'spatie/laravel-package-tools' => '^1.16',
    ])->and($composer['require-dev'])->toMatchArray([
        'orchestra/testbench' => '^11.0.0',
        'pestphp/pest'        => '^5.0',
        'laravel/pint'        => '^1.32',
        'larastan/larastan'   => '^3.10',
        'rector/rector'       => '^2.4',
        'peckphp/peck'        => '^0.3.0',
    ]);
});

it('purges the current package from the clear script', function (): void {
    expect(composerMetadata()['scripts']['clear'])
        ->toBe('@php vendor/bin/testbench package:purge-akira/efatura --ansi');
});

it('accepts the official DFA acronym in package tooling', function (): void {
    $peck = json_decode((string) file_get_contents(__DIR__ . '/../peck.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($peck['ignore']['words'])->toContain('dfa');
});
