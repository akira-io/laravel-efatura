<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\LoadRoutesConfig;
use Akira\Efatura\Configuration\RoutesConfig;
use Akira\Efatura\Exceptions\ConfigurationException;

function enabledRoutes(array $routes): array
{
    return ['enabled' => true, 'middleware' => ['auth'], ...$routes];
}

function loadRoutesConfig(array $routes): RoutesConfig
{
    config()->set('efatura.routes', $routes);

    return resolve(LoadRoutesConfig::class)();
}

it('ships the diagnostics routes disabled with safe defaults', function (): void {
    $routes = resolve(LoadRoutesConfig::class)();

    expect($routes->enabled)->toBeFalse()
        ->and($routes->prefix)->toBe('efatura')
        ->and($routes->name)->toBe('efatura.')
        ->and($routes->middleware)->toBe([])
        ->and($routes->maxUploadKilobytes)->toBe(5120)
        ->and($routes->remoteReadiness)->toBeFalse()
        ->and(config('efatura.routes'))->toBe([
            'enabled'              => false,
            'prefix'               => 'efatura',
            'name'                 => 'efatura.',
            'middleware'           => [],
            'max_upload_kilobytes' => 5120,
            'remote_readiness'     => false,
        ]);
});

it('reads enabled routes protected by middleware', function (): void {
    $routes = loadRoutesConfig([
        'enabled'              => true,
        'prefix'               => '/internal/efatura/',
        'name'                 => 'internal.efatura.',
        'middleware'           => ['auth:sanctum', 'can:efatura-diagnostics'],
        'max_upload_kilobytes' => '1024',
        'remote_readiness'     => true,
    ]);

    expect($routes->enabled)->toBeTrue()
        ->and($routes->prefix)->toBe('internal/efatura')
        ->and($routes->name)->toBe('internal.efatura.')
        ->and($routes->middleware)->toBe(['auth:sanctum', 'can:efatura-diagnostics'])
        ->and($routes->maxUploadKilobytes)->toBe(1024)
        ->and($routes->remoteReadiness)->toBeTrue();
});

it('reads nothing but the switch while the routes are disabled', function (array $routes): void {
    expect(loadRoutesConfig($routes))->toEqual(new RoutesConfig);
})->with([
    'a bad prefix'       => [['enabled' => false, 'prefix' => '../admin']],
    'no middleware'      => [['prefix' => 'efatura', 'middleware' => 'auth']],
    'a bad upload limit' => [['enabled' => false, 'max_upload_kilobytes' => 0]],
]);

it('inspects the whole section whether the routes are enabled or not', function (): void {
    config()->set('efatura.routes', ['enabled' => false, 'prefix' => '../admin']);

    expect(fn (): RoutesConfig => resolve(LoadRoutesConfig::class)->inspect())
        ->toThrow(ConfigurationException::class, 'configuration.invalid_route_prefix: efatura.routes.prefix');

    config()->set('efatura.routes', ['enabled' => false, 'prefix' => '/internal/', 'middleware' => [' auth ']]);

    expect(resolve(LoadRoutesConfig::class)->inspect())->toEqual(new RoutesConfig(prefix: 'internal', middleware: ['auth']));
});

it('names the key of a switch that is not a boolean', function (mixed $enabled): void {
    expect(fn (): RoutesConfig => loadRoutesConfig(['enabled' => $enabled, 'middleware' => ['auth']]))
        ->toThrow(ConfigurationException::class, 'configuration.invalid_type: efatura.routes.enabled');
})->with(['1', 'yes', 1, 'on']);

it('trims each middleware entry', function (): void {
    expect(loadRoutesConfig(['enabled' => true, 'middleware' => ['  auth:sanctum  ', "\tcan:efatura\n"]])->middleware)
        ->toBe(['auth:sanctum', 'can:efatura']);
});

it('rejects a routes section it cannot load safely', function (array $routes, string $errorCode, string $field): void {
    expect(fn (): RoutesConfig => loadRoutesConfig($routes))
        ->toThrow(function (ConfigurationException $exception) use ($errorCode, $field): void {
            expect($exception->errorCode)->toBe($errorCode)
                ->and($exception->field)->toBe($field);
        });
})->with([
    'enabled without middleware' => [['enabled' => true], 'configuration.unprotected_routes', 'efatura.routes.middleware'],
    'enabled with no entries'    => [['enabled' => true, 'middleware' => []], 'configuration.unprotected_routes', 'efatura.routes.middleware'],
    'enabled as a string'        => [['enabled' => 'yes', 'middleware' => ['auth']], 'configuration.invalid_type', 'efatura.routes.enabled'],
    'middleware not a list'      => [['enabled' => true, 'middleware' => 'auth'], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'middleware keyed'           => [['enabled' => true, 'middleware' => ['guard' => 'auth']], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'middleware blank entry'     => [['enabled' => true, 'middleware' => ['auth', ' ']], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'middleware not a string'    => [['enabled' => true, 'middleware' => [42]], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'empty prefix'               => [enabledRoutes(['prefix' => '/']), 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix with a parameter'    => [enabledRoutes(['prefix' => 'efatura/{tenant}']), 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix with an empty part'  => [enabledRoutes(['prefix' => 'internal//efatura']), 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix with a newline'      => [enabledRoutes(['prefix' => "efatura\n/"]), 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix not a string'        => [enabledRoutes(['prefix' => 1]), 'configuration.invalid_type', 'efatura.routes.prefix'],
    'name without a final dot'   => [enabledRoutes(['name' => 'efatura']), 'configuration.invalid_route_name', 'efatura.routes.name'],
    'name with a space'          => [enabledRoutes(['name' => 'e fatura.']), 'configuration.invalid_route_name', 'efatura.routes.name'],
    'name with a newline'        => [enabledRoutes(['name' => "efatura.\n."]), 'configuration.invalid_route_name', 'efatura.routes.name'],
    'upload limit of zero'       => [enabledRoutes(['max_upload_kilobytes' => 0]), 'configuration.invalid_integer', 'efatura.routes.max_upload_kilobytes'],
    'upload limit above 5 MiB'   => [enabledRoutes(['max_upload_kilobytes' => 5121]), 'configuration.invalid_integer', 'efatura.routes.max_upload_kilobytes'],
    'remote readiness as string' => [enabledRoutes(['remote_readiness' => 'true']), 'configuration.invalid_type', 'efatura.routes.remote_readiness'],
    'section not an array'       => [['enabled', true], 'configuration.invalid_type', 'efatura.routes'],
]);
