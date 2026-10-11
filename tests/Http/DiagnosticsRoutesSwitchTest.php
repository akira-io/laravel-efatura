<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\RoutesConfig;
use Akira\Efatura\EfaturaServiceProvider;
use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;
use Akira\Efatura\Tests\Support\DiagnosticsGuard;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\RouteFixtures;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('answers 404 from routes that stay registered after the switch is turned off', function (string $method, string $uri, array $data): void {
    RouteFixtures::enable();
    config()->set('efatura.routes.enabled', false);
    app()->forgetInstance(RoutesConfig::class);

    $this->json($method, $uri, $data, RouteFixtures::headers())->assertNotFound();
})->with([
    'readiness' => ['GET', '/efatura/readiness', []],
    'validate'  => ['POST', '/efatura/validate', fn (): array => [
        'file' => UploadedFile::fake()->createWithContent('document.xml', S::sign(S::unsigned())->xml),
    ]],
]);

it('registers no route while the application routes are cached', function (): void {
    app()->instance('routes.cached', true);
    config()->set('efatura.routes', ['enabled' => true, 'middleware' => [DiagnosticsGuard::class]]);

    app()->register(EfaturaServiceProvider::class, true);
    resolve(Router::class)->getRoutes()->refreshNameLookups();

    expect(app()->routesAreCached())->toBeTrue()
        ->and(resolve(Router::class)->has('efatura.readiness'))->toBeFalse();
});

it('boots and runs the console with a broken routes section that is disabled', function (): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    config()->set('efatura.routes', ['enabled' => false, 'prefix' => '../admin', 'middleware' => []]);
    app()->forgetInstance(RoutesConfig::class);
    Http::fake();

    app()->register(EfaturaServiceProvider::class, true);
    Artisan::call('efatura:doctor', ['--json' => true]);
    $checks = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'];

    expect(resolve(Router::class)->has('efatura.readiness'))->toBeFalse()
        ->and(collect($checks)->where('code', 'configuration.invalid_route_prefix')->values()->all())->toBe([[
            'check'    => ReadinessCheck::Configuration->value,
            'status'   => ReadinessStatus::Warning->value,
            'code'     => 'configuration.invalid_route_prefix',
            'field'    => 'efatura.routes.prefix',
            'required' => true,
            'context'  => [],
        ]]);
});
