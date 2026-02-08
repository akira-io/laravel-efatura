<?php

declare(strict_types=1);

use Akira\Efatura\Efatura;
use Akira\Efatura\Facades\Efatura as EfaturaFacade;

it('resolves facade accessor', function (): void {
    $reflection = new ReflectionMethod(EfaturaFacade::class, 'getFacadeAccessor');

    expect($reflection->invoke(null))->toBe(Efatura::class);
});
