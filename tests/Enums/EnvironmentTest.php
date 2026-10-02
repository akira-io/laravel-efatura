<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Environment;

it('resolves an environment from its name in any letter case', function (string $name, Environment $environment): void {
    expect(Environment::fromName($name))->toBe($environment);
})->with([
    ['test', Environment::Test],
    ['TEST', Environment::Test],
    ['Test', Environment::Test],
    ['homologation', Environment::Homologation],
    ['PRODUCTION', Environment::Production],
]);

it('resolves no environment from an unknown name', function (): void {
    expect(Environment::fromName('staging'))->toBeNull();
});

it('maps each environment to its official repository code', function (): void {
    expect(Environment::Production->code())->toBe(1)
        ->and(Environment::Homologation->code())->toBe(2)
        ->and(Environment::Test->code())->toBe(3);
});
