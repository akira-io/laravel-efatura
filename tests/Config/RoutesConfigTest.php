<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\LoadRoutesConfig;
use Akira\Efatura\Configuration\RoutesConfig;
use Akira\Efatura\Exceptions\ConfigurationException;

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

it('validates the routes section even while it is disabled', function (): void {
    expect(fn (): RoutesConfig => loadRoutesConfig(['prefix' => '../admin']))
        ->toThrow(ConfigurationException::class, 'configuration.invalid_route_prefix: efatura.routes.prefix');
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
    'middleware not a list'      => [['middleware' => 'auth'], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'middleware keyed'           => [['middleware' => ['guard' => 'auth']], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'middleware blank entry'     => [['middleware' => ['auth', ' ']], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'middleware not a string'    => [['middleware' => [42]], 'configuration.invalid_type', 'efatura.routes.middleware'],
    'empty prefix'               => [['prefix' => '/'], 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix with a parameter'    => [['prefix' => 'efatura/{tenant}'], 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix with an empty part'  => [['prefix' => 'internal//efatura'], 'configuration.invalid_route_prefix', 'efatura.routes.prefix'],
    'prefix not a string'        => [['prefix' => 1], 'configuration.invalid_type', 'efatura.routes.prefix'],
    'name without a final dot'   => [['name' => 'efatura'], 'configuration.invalid_route_name', 'efatura.routes.name'],
    'name with a space'          => [['name' => 'e fatura.'], 'configuration.invalid_route_name', 'efatura.routes.name'],
    'upload limit of zero'       => [['max_upload_kilobytes' => 0], 'configuration.invalid_integer', 'efatura.routes.max_upload_kilobytes'],
    'upload limit above 5 MiB'   => [['max_upload_kilobytes' => 5121], 'configuration.invalid_integer', 'efatura.routes.max_upload_kilobytes'],
    'remote readiness as string' => [['remote_readiness' => 'true'], 'configuration.invalid_type', 'efatura.routes.remote_readiness'],
    'section not an array'       => [['enabled', true], 'configuration.invalid_type', 'efatura.routes'],
]);
