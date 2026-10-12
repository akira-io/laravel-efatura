<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\EfaturaServiceProvider;
use Illuminate\Routing\Router;

final class RouteFixtures
{
    public static function enable(array $routes = []): void
    {
        config()->set('efatura.routes', ['enabled' => true, 'middleware' => [DiagnosticsGuard::class], ...$routes]);
        app()->register(EfaturaServiceProvider::class, true);
        resolve(Router::class)->getRoutes()->refreshNameLookups();
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [DiagnosticsGuard::HEADER => DiagnosticsGuard::TOKEN, 'Accept' => 'application/json'];
    }
}
