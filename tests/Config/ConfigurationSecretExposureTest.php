<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\CertificateConfig;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\OAuthConfig;
use Akira\Efatura\Configuration\TransmitterConfig;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Support\Secret;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

dataset('secret-bearing configurations', [
    'transmitter' => [
        static fn (): object => new TransmitterConfig('100200300', 'Transmitter', 'synthetic-middleware-key', new OAuthConfig('client', 'client-secret')),
        ['synthetic-middleware-key', 'client-secret'],
    ],
    'oauth'        => [static fn (): object => new OAuthConfig('client', 'synthetic-client-secret'), ['synthetic-client-secret']],
    'certificates' => [static fn (): object => new CertificateConfig('local', 'c.pem', 'k.pem', 'synthetic-passphrase'), ['synthetic-passphrase']],
    'efatura'      => [
        static fn (): object => ConfigFixtures::load([
            'transmitter' => [
                'middleware_key' => str_pad('synthetic-middleware-key', 64, '-key'),
                'oauth'          => ['client_id' => 'client', 'client_secret' => 'synthetic-client-secret'],
            ],
            'certificates' => ['passphrase' => 'synthetic-passphrase'],
        ]),
        ['synthetic-middleware-key', 'synthetic-client-secret', 'synthetic-passphrase'],
    ],
]);

dataset('exporters', [
    'var_export' => [static fn (object $value): string => var_export($value, true)],
    'print_r'    => [static fn (object $value): string => print_r($value, true)],
    'var_dump'   => [static function (object $value): string {
        ob_start();
        var_dump($value);

        return ob_get_clean() ?: '';
    }],
    'var_dump of the array cast' => [static function (object $value): string {
        ob_start();
        var_dump((array) $value);

        return ob_get_clean() ?: '';
    }],
    'print_r of the array cast'    => [static fn (object $value): string => print_r((array) $value, true)],
    'var_export of the array cast' => [static fn (object $value): string => var_export((array) $value, true)],
    'json_encode'                  => [static fn (object $value): string => (string) json_encode($value)],
    'symfony var-dumper'           => [static fn (object $value): string => (string) new CliDumper()->dump(new VarCloner()->cloneVar($value), true)],
]);

it('keeps every secret out of exported output', function (Closure $configuration, array $secrets, Closure $export): void {
    $output = $export($configuration());

    expect($output)->not->toBeEmpty();

    foreach ($secrets as $secret) {
        expect($output)->not->toContain($secret);
    }
})->with('secret-bearing configurations')->with('exporters');

it('keeps the value of a secret out of exported output', function (Closure $export): void {
    $output = $export(new Secret('synthetic-secret-value'));

    expect($output)->not->toBeEmpty();
    expect($output)->not->toContain('synthetic-secret-value');
})->with('exporters');

it('refuses to clone a secret', function (): void {
    expect(fn (): Secret => clone new Secret('synthetic-secret-value'))
        ->toThrow(DefinitionException::class, 'A secret cannot be serialized.');
});

it('refuses to serialize a configuration that holds a secret', function (Closure $configuration): void {
    expect(fn (): string => serialize($configuration()))
        ->toThrow(DefinitionException::class, 'Configuration holds secrets and cannot be serialized.');
})->with('secret-bearing configurations');

it('refuses to serialize a configuration whose secrets are absent', function (): void {
    expect(fn (): string => serialize(new CertificateConfig('local', null, null, null)))
        ->toThrow(DefinitionException::class, 'Configuration holds secrets and cannot be serialized.')
        ->and(fn (): string => serialize(ConfigFixtures::load()))
        ->toThrow(DefinitionException::class, 'Configuration holds secrets and cannot be serialized.');
});

it('refuses to unserialize a configuration that holds a secret', function (string $class): void {
    $payload = 'O:' . mb_strlen($class) . ':"' . $class . '":0:{}';

    expect(fn (): mixed => unserialize($payload))
        ->toThrow(DefinitionException::class, 'Configuration holds secrets and cannot be serialized.');
})->with([TransmitterConfig::class, OAuthConfig::class, CertificateConfig::class, EfaturaConfig::class]);

it('reveals each secret through its accessor', function (): void {
    $transmitter  = new TransmitterConfig('100200300', 'Transmitter', 'synthetic-middleware-key', new OAuthConfig('client', 'synthetic-client-secret'));
    $certificates = new CertificateConfig('local', 'c.pem', 'k.pem', 'synthetic-passphrase', 'ca.pem');

    expect($transmitter->middlewareKey())->toBe('synthetic-middleware-key')
        ->and($transmitter->oauth->clientSecret())->toBe('synthetic-client-secret')
        ->and($certificates->passphrase())->toBe('synthetic-passphrase')
        ->and($certificates->certificatePath)->toBe('c.pem')
        ->and($certificates->privateKeyPath)->toBe('k.pem')
        ->and($certificates->caBundlePath)->toBe('ca.pem');
});

it('reports absent secrets as null through their accessors', function (): void {
    $transmitter = new TransmitterConfig(null, null, null, new OAuthConfig(null, null));

    expect($transmitter->middlewareKey())->toBeNull()
        ->and($transmitter->oauth->clientSecret())->toBeNull()
        ->and(new CertificateConfig('local', null, null, null)->passphrase())->toBeNull();
});

it('keeps the secrets out of the public properties', function (string $class, string $property): void {
    expect(new ReflectionProperty($class, $property)->isPublic())->toBeFalse();
})->with([
    [TransmitterConfig::class, 'middlewareKey'],
    [OAuthConfig::class, 'clientSecret'],
    [CertificateConfig::class, 'passphrase'],
]);

it('refuses to reveal a secret that was never constructed', function (): void {
    $secret = new ReflectionClass(Secret::class)->newInstanceWithoutConstructor();

    expect(fn (): string => $secret->reveal())
        ->toThrow(DefinitionException::class, 'A secret cannot be serialized.');
});
