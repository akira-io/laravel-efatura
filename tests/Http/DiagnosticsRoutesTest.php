<?php

declare(strict_types=1);

use Akira\Efatura\EfaturaServiceProvider;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Http\Middleware\EnsureDiagnosticsRoutesEnabled;
use Akira\Efatura\Tests\Support\DiagnosticsGuard;
use Akira\Efatura\Tests\Support\RouteFixtures;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

function efaturaRoutes(): array
{
    return collect(resolve(Router::class)->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_contains((string) $route->getName(), 'readiness') || str_contains((string) $route->getName(), 'validate'))
        ->values()
        ->all();
}

it('registers no route by default', function (): void {
    expect(efaturaRoutes())->toBe([])
        ->and(resolve(Router::class)->has(['efatura.readiness', 'efatura.validate']))->toBeFalse();

    $this->getJson('/efatura/readiness')->assertNotFound();
});

it('registers the diagnostics routes with the configured prefix, name and middleware', function (): void {
    RouteFixtures::enable(['prefix' => '/internal/efatura/', 'name' => 'internal.efatura.', 'middleware' => [DiagnosticsGuard::class, 'throttle:10,1']]);

    $routes = collect(efaturaRoutes())->keyBy(fn (Route $route): string => (string) $route->getName());

    expect($routes->keys()->sort()->values()->all())->toBe(['internal.efatura.readiness', 'internal.efatura.validate'])
        ->and($routes['internal.efatura.readiness']->uri())->toBe('internal/efatura/readiness')
        ->and($routes['internal.efatura.readiness']->methods())->toBe(['GET', 'HEAD'])
        ->and($routes['internal.efatura.validate']->uri())->toBe('internal/efatura/validate')
        ->and($routes['internal.efatura.validate']->methods())->toBe(['POST'])
        ->and($routes['internal.efatura.validate']->gatherMiddleware())
        ->toBe([EnsureDiagnosticsRoutesEnabled::class, DiagnosticsGuard::class, 'throttle:10,1']);
});

it('refuses to boot with routes enabled and no middleware', function (): void {
    config()->set('efatura.routes', ['enabled' => true, 'middleware' => []]);

    expect(fn () => app()->register(EfaturaServiceProvider::class, true))
        ->toThrow(ConfigurationException::class, 'configuration.unprotected_routes: efatura.routes.middleware');
});

it('rejects a request the application middleware does not authorize', function (string $method, string $uri): void {
    RouteFixtures::enable();

    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'readiness' => ['GET', '/efatura/readiness'],
    'validate'  => ['POST', '/efatura/validate'],
]);

it('exposes no submission, dfa or xml building route', function (): void {
    RouteFixtures::enable();

    foreach (['submit', 'documents', 'events', 'dfa', 'xml'] as $uri) {
        $this->postJson('/efatura/' . $uri, headers: RouteFixtures::headers())->assertNotFound();
    }
});
