<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

beforeEach(function (): void {
    $this->guide = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/02-configuration.md');
});

it('documents every leaf key of the package configuration', function (): void {
    $keys = array_keys(Arr::dot(require dirname(__DIR__, 2) . '/config/efatura.php'));

    $undocumented = array_values(array_filter($keys, fn (string $key): bool => ! str_contains($this->guide, '`' . $key . '`')));

    expect($keys)->toContain('transmitter.oauth.client_id', 'queue.queue', 'http.platform.concurrency', 'routes.middleware')
        ->and($undocumented)->toBe([]);
});

it('documents every environment variable the configuration reads', function (): void {
    preg_match_all('/env\(\'(EFATURA_\w+)\'/', (string) file_get_contents(dirname(__DIR__, 2) . '/config/efatura.php'), $variables);

    $undocumented = array_values(array_filter($variables[1], fn (string $variable): bool => ! str_contains($this->guide, '`' . $variable . '`')));

    expect($variables[1])->not->toBeEmpty()
        ->and($undocumented)->toBe([]);
});
