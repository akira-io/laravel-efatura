<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Illuminate\Config\Repository;

final class ConfigFixtures
{
    /**
     * @param array<array-key, mixed> $overrides
     */
    public static function load(array $overrides = []): EfaturaConfig
    {
        return (new LoadEfaturaConfig(self::hostRepository($overrides)))();
    }

    /**
     * @param array<array-key, mixed> $overrides
     */
    public static function hostRepository(array $overrides = []): Repository
    {
        return new Repository([
            'filesystems' => ['default' => 'host-disk'],
            'cache'       => ['default' => 'host-cache'],
            'database'    => ['default' => 'host-database'],
            'queue'       => ['default' => 'host-queue', 'connections' => ['host-queue' => ['queue' => 'host-jobs']]],
            'efatura'     => $overrides,
        ]);
    }
}
