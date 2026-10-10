<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\OAuth\AccessToken;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->secret = 'access-' . bin2hex(random_bytes(12));
    $this->token  = new AccessToken($this->secret, CarbonImmutable::parse('2026-02-08T10:20:30Z'));
});

it('keeps its value out of dumps and json', function (): void {
    ob_start();
    var_dump($this->token);
    $dumped = (string) ob_get_clean();

    expect($dumped . print_r($this->token, true) . json_encode($this->token))->not->toContain($this->secret)
        ->and($this->token->jsonSerialize()['value'])->toBe('[redacted]')
        ->and($this->token->value)->toBe($this->secret);
});

it('refuses to be serialized', function (): void {
    expect(fn (): string => serialize($this->token))
        ->toThrow(DefinitionException::class, 'An access token is a secret and cannot be serialized.');
});
