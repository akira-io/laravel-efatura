<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\EfaturaValidationException;

it('exposes field and message', function (): void {
    $exception = new EfaturaValidationException('field', 'message');

    expect($exception->field())->toBe('field')
        ->and($exception->getMessage())->toBe('message');
});
