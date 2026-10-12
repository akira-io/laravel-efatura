<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Secret;

it('reveals the value it holds', function (): void {
    expect(new Secret('synthetic-secret')->reveal())->toBe('synthetic-secret')
        ->and(Secret::from('synthetic-secret')?->reveal())->toBe('synthetic-secret')
        ->and(Secret::from(null))->toBeNull();
});

it('keeps the value out of every dump', function (): void {
    $secret = new Secret('synthetic-secret');

    ob_start();
    var_dump($secret);
    $dumped = ob_get_clean() ?: '';

    expect(var_export($secret, true))->not->toContain('synthetic-secret')
        ->and(print_r($secret, true))->not->toContain('synthetic-secret')
        ->and($dumped)->not->toContain('synthetic-secret')
        ->and($secret->__debugInfo())->toBe(['value' => '[redacted]'])
        ->and(json_encode($secret))->toBe('"[redacted]"');
});

it('refuses to be serialized or unserialized', function (): void {
    $payload = 'O:' . mb_strlen(Secret::class) . ':"' . Secret::class . '":0:{}';

    expect(fn (): string => serialize(new Secret('synthetic-secret')))->toThrow(DefinitionException::class, 'A secret cannot be serialized.')
        ->and(fn (): mixed => unserialize($payload))->toThrow(DefinitionException::class, 'A secret cannot be serialized.');
});
