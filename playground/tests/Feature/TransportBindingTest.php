<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Testing\FakeTransport;

it('binds the package fake transport while the playground runs on the fake transport', function (): void {
    expect(resolve(Transport::class))->toBeInstanceOf(FakeTransport::class)
        ->and(resolve(Transport::class))->toBe(resolve(Transport::class));
});
