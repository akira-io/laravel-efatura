<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\ComposerMetadata;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('declares the runtime platform and the Laravel framework its foundation classes ship in', function (): void {
    $requirements = ComposerMetadata::read()['require'];

    expect($requirements)->toMatchArray([
        'php'               => '^8.5',
        'ext-curl'          => '*',
        'ext-dom'           => '*',
        'ext-libxml'        => '*',
        'ext-openssl'       => '*',
        'ext-zip'           => '*',
        'laravel/framework' => '^13.0',
    ])->and(array_filter(array_keys($requirements), fn (string $package): bool => str_starts_with($package, 'illuminate/')))->toBe([]);
});

it('declares the HTTP client its transport drives through the cURL handler', function (): void {
    expect(ComposerMetadata::read()['require'])->toMatchArray([
        'guzzlehttp/guzzle' => '^7.15.2 || ^8.2',
    ]);
});

it('retains the package runtime and development tools', function (): void {
    $composer = ComposerMetadata::read();

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

it('suggests the DFA renderers and installs them for development', function (): void {
    $composer = ComposerMetadata::read();

    expect(array_keys($composer['suggest']))->toBe(['dompdf/dompdf', 'bacon/bacon-qr-code'])
        ->and($composer['require'])->not->toHaveKeys(['dompdf/dompdf', 'bacon/bacon-qr-code'])
        ->and($composer['require-dev'])->toMatchArray([
            'dompdf/dompdf'       => '^3.1',
            'dompdf/php-font-lib' => '^1.0.2',
            'dompdf/php-svg-lib'  => '^1.0.2',
            'bacon/bacon-qr-code' => '^3.1',
        ]);
});

it('conflicts with DFA renderer versions older than the ones it supports', function (): void {
    expect(ComposerMetadata::read()['conflict'] ?? null)->toBe([
        'dompdf/dompdf'       => '<3.1',
        'bacon/bacon-qr-code' => '<3.1',
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

it('ships runtime resources and leaves development files out of dist archives', function (string $path, string $exported): void {
    $attribute = new Process(['git', 'check-attr', 'export-ignore', '--', $path], __DIR__ . '/..');
    $attribute->mustRun();

    expect(trim($attribute->getOutput()))->toBe($path . ': export-ignore: ' . $exported);
})->with([
    ['src/Efatura.php', 'unspecified'],
    ['config/efatura.php', 'unspecified'],
    ['database/migrations/create_efatura_sequences_table.php.stub', 'unspecified'],
    ['resources/lang/en/efatura.php', 'unspecified'],
    ['resources/lang/pt/efatura.php', 'unspecified'],
    ['routes/efatura.php', 'unspecified'],
    ['resources/catalogs/units.json', 'unspecified'],
    ['resources/official-artifacts.json', 'unspecified'],
    ['resources/views/dfa/document.blade.php', 'unspecified'],
    ['resources/xsd/efatura/2024-05-27/EnvelopedSignature.xsd', 'unspecified'],
    ['composer.json', 'unspecified'],
    ['tests', 'set'],
    ['tools', 'set'],
    ['docs', 'set'],
    ['.github', 'set'],
    ['resources/catalogs/source', 'set'],
    ['package.json', 'set'],
    ['bun.lock', 'set'],
    ['commitlint.config.js', 'set'],
    ['cliff.toml', 'set'],
    ['peck.json', 'set'],
]);
