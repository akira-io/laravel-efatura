<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\TrackedSources;

it('keeps every tracked source file within 300 lines', function (): void {
    $oversized = collect(TrackedSources::trackedFiles('src/*.php'))
        ->filter(fn (string $path): bool => count(file(TrackedSources::root() . '/' . $path) ?: []) > 300)
        ->values()
        ->all();

    expect(TrackedSources::trackedFiles('src/*.php'))->not->toBeEmpty()
        ->and($oversized)->toBe([]);
});

it('never declares, casts or writes a float where fiscal amounts are handled', function (string $pattern): void {
    $files = TrackedSources::trackedFiles($pattern);

    expect($files)->not->toBeEmpty()
        ->and(collect($files)->flatMap(fn (string $path): array => TrackedSources::floats($path))->all())->toBe([]);
})->with([
    'src/Money/*.php',
    'src/Data/*.php',
    'src/Casts/*.php',
    'src/Transformers/*.php',
    'src/DataPipes/*.php',
    'src/Rules/*.php',
    'src/Currency/*.php',
    'src/Dfa/*.php',
    'src/Builders/*.php',
    'src/Xml/*.php',
    'src/Actions/*.php',
    'src/Support/*.php',
]);

it('finds a float wherever one is written', function (string $source, int $count): void {
    expect(TrackedSources::floats('probe.php', "<?php\n" . $source))->toHaveCount($count);
})->with([
    'literal'          => ['$amount = 1.5;', 1],
    'exponent literal' => ['$amount = 1e3;', 1],
    'cast'             => ['$amount = (float) $value;', 1],
    'parameter type'   => ['function amount(float $value): string { return ""; }', 1],
    'conversion'       => ['$amount = floatval($value);', 1],
    'decimal string'   => ['$amount = "1.5";', 0],
    'integer'          => ['$amount = 15;', 0],
]);

it('keeps names of other projects, their tooling and other Manual versions out of tracked files', function (): void {
    $scanned = array_filter(
        TrackedSources::trackedFiles(),
        fn (string $path): bool => ! preg_match('#^(resources/(xsd|catalogs/source)/|tests/Release/|tests/ArchitectureLimitsTest\.php$|tests/Support/TrackedSources\.php$)#', $path)
            && ! preg_match('#\.(zip|xlsx?|png|jpe?g|gif|pdf|ico|woff2?|ttf)$#', $path),
    );

    $mentions = collect($scanned)
        ->filter(fn (string $path): bool => preg_match(TrackedSources::BANNED_MENTIONS, (string) file_get_contents(TrackedSources::root() . '/' . $path)) === 1)
        ->values()
        ->all();

    expect($scanned)->not->toBeEmpty()
        ->and($mentions)->toBe([]);
});

it('recognises every banned mention and leaves look-alikes alone', function (string $text, int $matches): void {
    expect(preg_match(TrackedSources::BANNED_MENTIONS, $text))->toBe($matches);
})->with([
    'sibling project'  => ['ported from node-efatura', 1],
    'release tooling'  => ['configured by release-it', 1],
    'package manager'  => ['run pnpm install', 1],
    'version claim'    => ['supports the v10 layout', 1],
    'patch version'    => ['since v10.2', 1],
    'current manual'   => ['Manual v11', 0],
    'openssl provider' => ['the OpenSSL legacy provider', 0],
    'encoded value'    => ['MIIBv10Aw+v10/x', 0],
    'larger version'   => ['tag v100', 0],
]);
