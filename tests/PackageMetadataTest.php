<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function composerMetadata(): array
{
    return (new Filesystem)->json(__DIR__ . '/../composer.json', JSON_THROW_ON_ERROR);
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

it('runs the clear script as a registered Testbench command', function (): void {
    $clearHelp = new Process(['composer', 'run', 'clear', '--', '--help'], __DIR__ . '/..');
    $clearHelp->run();

    expect($clearHelp->isSuccessful())->toBeTrue()
        ->and($clearHelp->getOutput())->toContain('Usage:')->toContain('package:purge-skeleton');
});

it('accepts the official DFA acronym in package tooling', function (): void {
    $peck = (new Filesystem)->json(__DIR__ . '/../peck.json', JSON_THROW_ON_ERROR);

    expect($peck['ignore']['words'])->toContain('dfa');
});
