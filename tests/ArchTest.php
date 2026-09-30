<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'ad', 'dieAndDump'])
    ->each->not->toBeUsed();

arch('runtime configuration never reads environment variables')
    ->expect('Akira\Efatura')
    ->not->toUse('env');

arch('configuration values are immutable')
    ->expect('Akira\Efatura\Configuration')
    ->toBeReadonly();
