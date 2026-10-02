<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'ad', 'dieAndDump'])
    ->each->not->toBeUsed();

arch('runtime configuration never reads environment variables')
    ->expect('Akira\Efatura')
    ->not->toUse('env');

arch('configuration values are immutable')
    ->expect('Akira\Efatura\Configuration')
    ->toBeReadonly();

it('keeps source and tooling lines within 160 characters', function (): void {
    $longLines = collect(new Filesystem()->allFiles(__DIR__ . '/../src'))
        ->merge(new Filesystem()->allFiles(__DIR__ . '/../tools'))
        ->flatMap(fn (SplFileInfo $file): array => collect(file($file->getPathname()) ?: [])
            ->filter(fn (string $line): bool => mb_strlen(rtrim($line, "\n")) > 160)
            ->keys()
            ->map(fn (int $index): string => $file->getFilename() . ':' . ($index + 1))
            ->all())
        ->all();

    expect($longLines)->toBe([]);
});
