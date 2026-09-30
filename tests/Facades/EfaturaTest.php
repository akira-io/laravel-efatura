<?php

declare(strict_types=1);

use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Facades\Efatura as EfaturaFacade;

it('resolves the singleton manager through the facade', function (): void {
    $manager = resolve(EfaturaManager::class);

    expect(EfaturaFacade::getFacadeRoot())->toBe($manager)
        ->and(EfaturaFacade::config())->toBe($manager->config());
});
