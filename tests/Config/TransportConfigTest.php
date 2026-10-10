<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Enums\TransportDriver;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Illuminate\Support\Arr;

it('submits through the middleware unless another driver is configured', function (): void {
    expect(ConfigFixtures::load()->driver)->toBe(TransportDriver::Middleware);
});

it('reads the driver by name in any letter case or as an enum case', function (mixed $configured, TransportDriver $driver): void {
    expect(ConfigFixtures::load(['driver' => $configured])->driver)->toBe($driver);
})->with([
    'upper case name' => ['MIDDLEWARE', TransportDriver::Middleware],
    'padded name'     => [' platform ', TransportDriver::Platform],
    'enum case'       => [TransportDriver::Platform, TransportDriver::Platform],
]);

it('reads the oauth issuer of the platform with the official default', function (): void {
    expect(ConfigFixtures::load()->transmitter->oauth->issuerUrl)->toBe(Fiscal::OAUTH_ISSUER_URL)
        ->and(ConfigFixtures::load(['transmitter' => ['oauth' => ['issuer_url' => 'https://iam.example.test/realms/fiscal/']]])->transmitter->oauth->issuerUrl)
        ->toBe('https://iam.example.test/realms/fiscal');
});

it('accepts a middleware key of exactly the official length', function (): void {
    $key = str_repeat('a1', Fiscal::TRANSMITTER_KEY_LENGTH / 2);

    expect(ConfigFixtures::load(['transmitter' => ['middleware_key' => $key]])->transmitter->middlewareKey)->toBe($key);
});

it('rejects transport settings with a stable error that never repeats the value', function (string $field, mixed $value, string $errorCode): void {
    $original = ini_set('zend.exception_ignore_args', '0');
    $loader   = new LoadEfaturaConfig(ConfigFixtures::hostRepository(Arr::undot([$field => $value])));

    try {
        expect(fn (): EfaturaConfig => $loader())
            ->toThrow(function (ConfigurationException $exception) use ($field, $value, $errorCode): void {
                expect($exception->errorCode)->toBe($errorCode)
                    ->and($exception->field)->toBe('efatura.' . $field)
                    ->and($exception->getMessage())->toBe($errorCode . ': efatura.' . $field)
                    ->and(print_r(ExceptionTrace::packageArguments($exception), true))->not->toContain(is_string($value) ? $value : '[unused]');
            });
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }
})->with([
    'unknown driver'     => ['driver', 'soap', 'configuration.invalid_driver'],
    'driver code'        => ['driver', 1, 'configuration.invalid_driver'],
    'null driver'        => ['driver', null, 'configuration.invalid_driver'],
    'short key'          => ['transmitter.middleware_key', str_repeat('k', 63), 'configuration.invalid_length'],
    'long key'           => ['transmitter.middleware_key', str_repeat('k', 65), 'configuration.invalid_length'],
    'plain http issuer'  => ['transmitter.oauth.issuer_url', 'http://iam.example.test/realms/fiscal', 'configuration.unsafe_url'],
    'issuer credentials' => ['transmitter.oauth.issuer_url', 'https://user:secret@iam.example.test', 'configuration.unsafe_url'],
    'issuer query'       => ['transmitter.oauth.issuer_url', 'https://iam.example.test/realms?client=x', 'configuration.unsafe_url'],
    'null issuer'        => ['transmitter.oauth.issuer_url', null, 'configuration.invalid_type'],
]);
