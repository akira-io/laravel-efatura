<?php

declare(strict_types=1);

use Akira\Efatura\Actions\SubmitArchiveAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Testing\FakeTransport;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\MiddlewareTransport;
use Akira\Efatura\Transport\PlatformTransport;
use Akira\Efatura\Transport\TransportResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function resolverConfig(string $driver = 'middleware', ?string $key = null): EfaturaConfig
{
    $config = ConfigFixtures::load(['driver' => $driver, 'environment' => 'test', 'transmitter' => ['middleware_key' => $key]]);
    app()->instance(EfaturaConfig::class, $config);

    return $config;
}

it('resolves the configured driver', function (string $driver, string $transport): void {
    resolverConfig($driver);
    app()->instance(AccessTokenProvider::class, new RecordingAccessTokens('resolver'));

    expect(resolve(TransportResolver::class)->driver())->toBeInstanceOf($transport)
        ->and(resolve(Transport::class))->toBeInstanceOf($transport);
})->with([
    'the middleware by default' => ['middleware', MiddlewareTransport::class],
    'the platform'              => ['platform', PlatformTransport::class],
]);

it('resolves the driver asked for over the configured one', function (): void {
    resolverConfig();
    app()->instance(AccessTokenProvider::class, new RecordingAccessTokens('resolver'));

    expect(resolve(TransportResolver::class)->driver(TransportDriver::Platform))->toBeInstanceOf(PlatformTransport::class);
});

it('refuses the platform without an access token provider', function (): void {
    resolverConfig('platform');

    expect(fn (): Transport => resolve(TransportResolver::class)->driver())->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::MissingCredentials)
            ->and($exception->field)->toBe('efatura.transmitter.oauth')
            ->and($exception->context)->toBe([]);
    });
});

it('builds the transport with the configuration it was given', function (): void {
    resolverConfig(key: str_repeat('a', 64));
    $scoped = ConfigFixtures::load(['environment' => 'test', 'transmitter' => ['middleware_key' => str_repeat('b', 64)]]);
    Http::fake(['*' => Http::response(T::response('accepted'))]);

    resolve(TransportResolver::class)->withConfig($scoped)->driver()->submit(T::archive());

    Http::assertSent(fn (Request $request): bool => $request->header('cv-ef-mw-core-transmitter-key') === [str_repeat('b', 64)]);
});

it('lets a transport bound by the application replace the configured one', function (): void {
    resolverConfig();
    $fake = resolve(FakeTransport::class);
    app()->instance(Transport::class, $fake);

    resolve(SubmitArchiveAction::class)->handle(T::archive());

    expect(resolve(Transport::class))->toBe($fake)
        ->and($fake->submitted())->toHaveCount(1);
});
