<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\ComposerMetadata;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->root = dirname(__DIR__, 2);
});

it('targets PHP 8.5, Laravel 13 and Pest 5', function (): void {
    $composer = ComposerMetadata::read();

    expect($composer['require'])->toMatchArray([
        'php'                  => '^8.5',
        'illuminate/contracts' => '^13.0',
        'illuminate/support'   => '^13.0',
    ])->and($composer['require-dev'])->toMatchArray([
        'orchestra/testbench' => '^11.0.0',
        'pestphp/pest'        => '^5.0',
    ]);
});

it('keeps a single license declaration per license', function (string $path, bool $present): void {
    expect(file_exists($this->root . '/' . $path))->toBe($present);
})->with([
    ['LICENSE-MIT', true],
    ['LICENSE-APACHE', true],
    ['LICENSE.md', false],
]);

it('declares the dual license in composer.json and package.json', function (): void {
    $package = (new Filesystem)->json($this->root . '/package.json', JSON_THROW_ON_ERROR);

    expect(ComposerMetadata::read()['license'])->toBe(['MIT', 'Apache-2.0'])
        ->and($package['license'])->toBe('(MIT OR Apache-2.0)');
});

it('manages the commit tooling with bun alone', function (string $path): void {
    expect(file_exists($this->root . '/' . $path))->toBeFalse();
})->with([
    'package-lock.json',
    'npm-shrinkwrap.json',
    'pnpm-lock.yaml',
    'pnpm-workspace.yaml',
    'yarn.lock',
    '.release-it.json',
    '.release-it.js',
    '.release-it.yml',
    '.npmrc',
]);

it('keeps package.json a private bun manifest for the commit tooling', function (): void {
    $package = (new Filesystem)->json($this->root . '/package.json', JSON_THROW_ON_ERROR);

    expect($package['private'] ?? null)->toBeTrue()
        ->and($package['packageManager'])->toStartWith('bun@')
        ->and($package)->not->toHaveKeys(['main', 'directories', 'release-it', 'version'])
        ->and($package['scripts'])->toBe(['commitlint' => 'commitlint --edit'])
        ->and(array_keys($package['devDependencies']))->toBe(['@commitlint/cli', '@commitlint/config-conventional'])
        ->and(file_exists($this->root . '/bun.lock'))->toBeTrue();
});

it('opens the changelog with the header git-cliff writes', function (): void {
    $cliff = (string) file_get_contents($this->root . '/cliff.toml');
    preg_match('/^header = """(.*?)"""/ms', $cliff, $header);

    expect($header)->toHaveCount(2)
        ->and((string) file_get_contents($this->root . '/CHANGELOG.md'))->toStartWith($header[1]);
});
