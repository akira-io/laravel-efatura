<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Illuminate\Config\Repository;

it('redacts raw configuration from every captured exception argument', function (string $field, mixed $value): void {
    $original = ini_set('zend.exception_ignore_args', '0');

    try {
        $repository = new Repository(config()->all());
        $repository->set('efatura.certificates.passphrase', 'synthetic-signing-passphrase');
        $repository->set('efatura.http.platform.base_url', 'https://synthetic-user:synthetic-password@example.test');
        $repository->set($field, match ($value) {
            'url'     => 'https://synthetic-user:synthetic-password@example.test',
            'raw'     => ['synthetic-raw-secret'],
            'emitter' => ['name' => ['synthetic-raw-secret']],
            default   => $value,
        });

        expect(fn (): mixed => (new LoadEfaturaConfig($repository))())->toThrow(function (ConfigurationException $exception): void {
            $arguments = ExceptionTrace::packageArguments($exception);
            $dump      = print_r($arguments, true);

            expect($arguments)->not->toBeEmpty()
                ->and($dump)->not->toContain('synthetic-signing-passphrase')
                ->and($dump)->not->toContain('synthetic-user')
                ->and($dump)->not->toContain('synthetic-password')
                ->and($dump)->not->toContain('synthetic-raw-secret');
        });
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }
})->with([
    'inherited certificate disk' => ['efatura.certificates.disk', false],
    'global HTTP defaults'       => ['efatura.http.timeout_seconds', 0],
    'client URL credentials'     => ['efatura.http.middleware.base_url', 'url'],
    'raw environment value'      => ['efatura.environment', 'raw'],
    'raw emitter value'          => ['efatura.emitter', 'emitter'],
    'raw oauth section'          => ['efatura.transmitter.oauth', 'raw'],
    'raw software section'       => ['efatura.software', 'raw'],
]);
