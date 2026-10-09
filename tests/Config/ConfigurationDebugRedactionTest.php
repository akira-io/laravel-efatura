<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\CertificateConfig;
use Akira\Efatura\Configuration\OAuthConfig;
use Akira\Efatura\Configuration\TransmitterConfig;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Monolog\Formatter\NormalizerFormatter;

dataset('debug outputs', [
    'print_r'  => [static fn (object $value): string => print_r($value, true)],
    'var_dump' => [static function (object $value): string {
        ob_start();
        var_dump($value);

        return (string) ob_get_clean();
    }],
    'json_encode'        => [static fn (object $value): string => (string) json_encode($value)],
    'monolog normalizer' => [static fn (object $value): string => (string) json_encode(new NormalizerFormatter()->normalizeValue($value))],
]);

it('redacts configured secrets from debug output', function (Closure $dump): void {
    $config = ConfigFixtures::load([
        'transmitter'  => ['tax_id' => '100200300', 'middleware_key' => 'synthetic-middleware-key', 'oauth' => ['client_id' => 'client', 'client_secret' => 'synthetic-client-secret']],
        'certificates' => ['passphrase' => 'synthetic-passphrase'],
    ]);

    $output = $dump($config);

    expect($output)->not->toContain('synthetic-middleware-key')
        ->and($output)->not->toContain('synthetic-client-secret')
        ->and($output)->not->toContain('synthetic-passphrase')
        ->and($output)->toContain('[redacted]')
        ->and($output)->toContain('100200300')
        ->and($output)->toContain('client');
})->with('debug outputs');

it('keeps the secrets readable as properties', function (): void {
    $transmitter = new TransmitterConfig('100200300', 'Transmitter', 'synthetic-middleware-key', new OAuthConfig('client', 'synthetic-client-secret'));

    expect($transmitter->middlewareKey)->toBe('synthetic-middleware-key')
        ->and($transmitter->oauth->clientSecret)->toBe('synthetic-client-secret');
});

it('reports absent secrets as null in debug output', function (): void {
    expect(new CertificateConfig('disk', null, null, null)->__debugInfo())->toBe([
        'disk'            => 'disk',
        'certificatePath' => null,
        'privateKeyPath'  => null,
        'passphrase'      => null,
    ])->and(new OAuthConfig('client', 'synthetic-client-secret')->__debugInfo())->toBe([
        'clientId'     => 'client',
        'clientSecret' => '[redacted]',
    ]);
});

it('redacts the secrets of each configuration from its json form', function (): void {
    expect(json_encode(new TransmitterConfig('100200300', 'Transmitter', 'synthetic-middleware-key', new OAuthConfig('client', 'synthetic-client-secret'))))
        ->toBe('{"taxId":"100200300","name":"Transmitter","middlewareKey":"[redacted]","oauth":{"clientId":"client","clientSecret":"[redacted]"}}')
        ->and(json_encode(new CertificateConfig('disk', 'cert.pem', 'key.pem', 'synthetic-passphrase')))
        ->toBe('{"disk":"disk","certificatePath":"cert.pem","privateKeyPath":"key.pem","passphrase":"[redacted]"}');
});
