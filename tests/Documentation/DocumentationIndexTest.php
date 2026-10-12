<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\Guides;

it('numbers the guides in reading order from the index', function (): void {
    $names = Guides::names();

    expect($names[0])->toBe(Guides::INDEX)
        ->and($names)->each->toMatch('/^\d{2}-[a-z\d]+(-[a-z\d]+)*\.md$/')
        ->and(array_map(fn (string $name): int => (int) substr($name, 0, 2), $names))->toBe(range(0, count($names) - 1))
        ->and($names)->toContain(
            '01-installation.md',
            '02-configuration.md',
            '15-console.md',
            '17-troubleshooting.md',
            '18-compliance-manual-v11.md',
        );
});

it('lists every guide in the index and in the README', function (): void {
    $index  = Guides::read(Guides::INDEX);
    $readme = (string) file_get_contents(dirname(__DIR__, 2) . '/README.md');

    foreach (array_slice(Guides::names(), 1) as $name) {
        expect($index)->toContain('](' . $name . ')');
    }

    foreach (Guides::names() as $name) {
        expect($readme)->toContain('](docs/' . $name . ')');
    }
});

it('ends every guide with a navigation footer', function (string $name): void {
    expect(Guides::read($name))->toMatch(Guides::footer($name));
})->with(fn (): array => Guides::names());

it('links only to files and headings that exist', function (string $file): void {
    expect(Guides::brokenLinks($file))->toBe([]);
})->with(fn (): array => collect([...glob(Guides::directory() . '/*.md') ?: [], dirname(__DIR__, 2) . '/README.md'])
    ->mapWithKeys(fn (string $path): array => [basename($path) => [$path]])
    ->all());

it('reports a link to a missing guide or heading as broken', function (): void {
    $probe = sys_get_temp_dir() . '/efatura-guide-' . bin2hex(random_bytes(4)) . '.md';
    file_put_contents($probe, "# Probe\n\n[gone](missing.md) [here](#probe) [nowhere](#absent) [web](https://example.com/x)\n");

    try {
        expect(Guides::brokenLinks($probe))->toBe([basename($probe) . ' -> missing.md', basename($probe) . ' -> #absent']);
    } finally {
        unlink($probe);
    }
});
