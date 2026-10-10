<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

it('references every packaged translation key from the source', function (string $key): void {
    $source = collect(new Filesystem()->allFiles(__DIR__ . '/../src'))
        ->map(fn (SplFileInfo $file): string => (string) file_get_contents($file->getPathname()))
        ->implode(PHP_EOL);

    $leaf = Str::afterLast($key, '.');

    expect(Str::contains($source, ['efatura::efatura.' . $key, var_export($leaf, true)]))->toBeTrue();
})->with(fn (): array => array_keys(Arr::dot(require __DIR__ . '/../resources/lang/en/efatura.php')));
