<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Illuminate\Config\Repository;

it('resolves minimal host defaults without optional identities or secrets', function (): void {
    $config = ConfigFixtures::load();

    expect($config->environment->environment)->toBe(Environment::Test)
        ->and($config->environment->repositoryCode())->toBe(3)
        ->and($config->emitter)->toBeNull()
        ->and($config->transmitter->taxId)->toBeNull()
        ->and($config->transmitter->middlewareKey)->toBeNull()
        ->and($config->transmitter->oauth->clientId)->toBeNull()
        ->and($config->transmitter->oauth->clientSecret)->toBeNull()
        ->and($config->software->code)->toBeNull()
        ->and($config->certificates->disk)->toBe('host-disk')
        ->and($config->certificates->certificatePath)->toBeNull()
        ->and($config->certificates->privateKeyPath)->toBeNull()
        ->and($config->certificates->passphrase)->toBeNull()
        ->and($config->storage->disk)->toBe('host-disk')
        ->and($config->storage->path)->toBe('efatura')
        ->and($config->cache->store)->toBe('host-cache')
        ->and($config->cache->prefix)->toBe('efatura')
        ->and($config->cache->exchangeRatesTtlSeconds)->toBe(3600)
        ->and($config->database->connection)->toBe('host-database')
        ->and($config->database->sequencesTable)->toBe('efatura_sequences')
        ->and($config->queue->connection)->toBe('host-queue')
        ->and($config->queue->queue)->toBe('host-jobs')
        ->and($config->http->middleware->baseUrl)->toBe('https://localhost:3443')
        ->and($config->http->platform->baseUrl)->toBe('https://services.efatura.cv/v1')
        ->and($config->http->bcv->baseUrl)->toBeNull()
        ->and($config->http->worldBank->baseUrl)->toBeNull();
});

it('resolves the default transport settings for each HTTP client', function (string $client): void {
    $settings = ConfigFixtures::load()->http->{$client};

    expect($settings->timeoutSeconds)->toBe(30)
        ->and($settings->connectTimeoutSeconds)->toBe(10)
        ->and($settings->retries)->toBe(2)
        ->and($settings->retryDelayMilliseconds)->toBe(200)
        ->and($settings->concurrency)->toBe(5)
        ->and($settings->verifyTls)->toBeTrue();
})->with([
    'defaults'   => 'defaults',
    'middleware' => 'middleware',
    'platform'   => 'platform',
    'bcv'        => 'bcv',
    'world bank' => 'worldBank',
]);

it('boots with the published configuration and no operation credentials', function (): void {
    $config = (new LoadEfaturaConfig(resolve('config')))();

    expect($config->emitter)->toBeNull()
        ->and($config->transmitter->middlewareKey)->toBeNull()
        ->and($config->transmitter->oauth->clientSecret)->toBeNull()
        ->and($config->certificates->privateKeyPath)->toBeNull()
        ->and($config->environment->repositoryCode())->toBe(3);
});

it('keeps published defaults aligned with loader defaults', function (): void {
    expect(ConfigFixtures::load(require __DIR__ . '/../../config/efatura.php'))->toEqual(ConfigFixtures::load());
});

it('retains partial emitter defaults independently from transmitter identity', function (): void {
    $config = ConfigFixtures::load([
        'emitter'     => ['contacts' => ['email' => 'fiscal@example.test']],
        'transmitter' => ['tax_id' => '100200300', 'name' => 'Transmitter'],
    ]);

    expect($config->emitter->taxId)->toBeNull()
        ->and($config->emitter->name)->toBeNull()
        ->and($config->emitter->contacts->email)->toBe('fiscal@example.test');
});

it('normalizes configured identities infrastructure and client overrides', function (): void {
    $config = ConfigFixtures::load([
        'environment' => ' production ',
        'emitter'     => [
            'tax_id'   => '100200300', 'name' => ' Fiscal party ', 'led' => '12',
            'address'  => ['country_code' => 'CV', 'region' => 'Santiago', 'city' => 'Praia', 'street' => 'Rua 1', 'postal_code' => '7600'],
            'contacts' => ['email' => 'fiscal@example.test', 'telephone' => '2600000', 'mobile' => '9900000'],
        ],
        'transmitter' => [
            'tax_id' => '100200300', 'name' => 'Transmission party', 'middleware_key' => ' test-key ',
            'oauth'  => ['client_id' => 'test-client', 'client_secret' => ' test-secret '],
        ],
        'software'     => ['code' => 'SW-1', 'name' => 'Fiscal software', 'version' => '1.0'],
        'certificates' => ['disk' => 'secret-disk', 'certificate_path' => 'certs/public.pem', 'private_key_path' => 'certs/private.pem', 'passphrase' => ' test-passphrase '],
        'storage'      => ['disk' => 'fiscal-disk', 'path' => 'tenant/fiscal'],
        'cache'        => ['store' => 'fiscal-cache', 'prefix' => 'tenant:fiscal', 'exchange_rates_ttl_seconds' => '120'],
        'database'     => ['connection' => 'fiscal-database', 'sequences_table' => 'tenant_sequences'],
        'queue'        => ['connection' => 'fiscal-queue', 'queue' => 'fiscal-jobs'],
        'http'         => [
            'timeout_seconds'          => '45', 'connect_timeout_seconds' => 15, 'retries' => 0,
            'retry_delay_milliseconds' => 0, 'concurrency' => 3, 'verify_tls' => false,
            'middleware'               => ['base_url' => 'https://middleware.example.test/api/', 'timeout_seconds' => 90],
            'platform'                 => ['connect_timeout_seconds' => 20, 'retries' => 4],
            'bcv'                      => ['retry_delay_milliseconds' => 400, 'concurrency' => 1],
            'world_bank'               => ['base_url' => 'https://rates.example.test', 'verify_tls' => true],
        ],
    ]);

    expect($config->environment->repositoryCode())->toBe(1)
        ->and($config->emitter->taxId)->toBe('100200300')
        ->and($config->emitter->name)->toBe('Fiscal party')
        ->and($config->emitter->led)->toBe(12)
        ->and($config->emitter->address->countryCode)->toBe('CV')
        ->and($config->emitter->address->region)->toBe('Santiago')
        ->and($config->emitter->address->city)->toBe('Praia')
        ->and($config->emitter->address->street)->toBe('Rua 1')
        ->and($config->emitter->address->postalCode)->toBe('7600')
        ->and($config->emitter->contacts->email)->toBe('fiscal@example.test')
        ->and($config->emitter->contacts->telephone)->toBe('2600000')
        ->and($config->emitter->contacts->mobile)->toBe('9900000')
        ->and($config->transmitter->name)->toBe('Transmission party')
        ->and($config->transmitter->taxId)->toBe($config->emitter->taxId)
        ->and($config->transmitter->middlewareKey)->toBe(' test-key ')
        ->and($config->transmitter->oauth->clientId)->toBe('test-client')
        ->and($config->transmitter->oauth->clientSecret)->toBe(' test-secret ')
        ->and($config->software->code)->toBe('SW-1')
        ->and($config->software->name)->toBe('Fiscal software')
        ->and($config->software->version)->toBe('1.0')
        ->and($config->certificates->disk)->toBe('secret-disk')
        ->and($config->certificates->certificatePath)->toBe('certs/public.pem')
        ->and($config->certificates->privateKeyPath)->toBe('certs/private.pem')
        ->and($config->certificates->passphrase)->toBe(' test-passphrase ')
        ->and($config->storage->disk)->toBe('fiscal-disk')
        ->and($config->storage->path)->toBe('tenant/fiscal')
        ->and($config->cache->store)->toBe('fiscal-cache')
        ->and($config->cache->prefix)->toBe('tenant:fiscal')
        ->and($config->cache->exchangeRatesTtlSeconds)->toBe(120)
        ->and($config->database->connection)->toBe('fiscal-database')
        ->and($config->database->sequencesTable)->toBe('tenant_sequences')
        ->and($config->queue->connection)->toBe('fiscal-queue')
        ->and($config->queue->queue)->toBe('fiscal-jobs')
        ->and($config->http->defaults->timeoutSeconds)->toBe(45)
        ->and($config->http->middleware->baseUrl)->toBe('https://middleware.example.test/api')
        ->and($config->http->middleware->timeoutSeconds)->toBe(90)
        ->and($config->http->platform->timeoutSeconds)->toBe(45)
        ->and($config->http->platform->connectTimeoutSeconds)->toBe(20)
        ->and($config->http->platform->retries)->toBe(4)
        ->and($config->http->bcv->retryDelayMilliseconds)->toBe(400)
        ->and($config->http->bcv->concurrency)->toBe(1)
        ->and($config->http->bcv->verifyTls)->toBeFalse()
        ->and($config->http->worldBank->baseUrl)->toBe('https://rates.example.test')
        ->and($config->http->worldBank->verifyTls)->toBeTrue();
});

it('accepts official environment names codes and enum cases', function (mixed $environment, int $code): void {
    expect(ConfigFixtures::load(['environment' => $environment])->environment->repositoryCode())->toBe($code);
})->with([[Environment::Homologation, 2], ['homologation', 2], [1, 1], ['1', 1], ['2', 2], ['3', 3]]);

it('trims ASCII edge whitespace while preserving non-breaking spaces and secrets', function (): void {
    $nonBreaking = "\u{00A0}name\u{00A0}";
    $config      = ConfigFixtures::load([
        'software'    => ['name' => $nonBreaking, 'code' => " \tcode\r\n"],
        'transmitter' => ['middleware_key' => " \tsecret\r\n", 'oauth' => ['client_secret' => "\u{00A0}secret\u{00A0}"]],
    ]);

    expect($config->software->name)->toBe($nonBreaking)
        ->and($config->software->code)->toBe('code')
        ->and($config->transmitter->middlewareKey)->toBe(" \tsecret\r\n")
        ->and($config->transmitter->oauth->clientSecret)->toBe("\u{00A0}secret\u{00A0}");
});

it('inherits the queue name of an explicitly selected queue connection', function (): void {
    $repository = new Repository([
        'filesystems' => ['default' => 'local'], 'cache' => ['default' => 'array'],
        'database'    => ['default' => 'sqlite'],
        'queue'       => ['default' => 'sync', 'connections' => ['redis' => ['queue' => 'priority']]],
        'efatura'     => ['emitter' => null, 'queue' => ['connection' => 'redis', 'queue' => null], 'http' => ['middleware' => ['timeout_seconds' => null]]],
    ]);

    expect((new LoadEfaturaConfig($repository))()->queue->queue)->toBe('priority');
});

it('leaves the queue name empty when the inherited host connection declares none', function (): void {
    $repository = new Repository([
        'filesystems' => ['default' => 'local'], 'cache' => ['default' => 'array'],
        'database'    => ['default' => 'sqlite'],
        'queue'       => ['default' => 'sync', 'connections' => ['redis' => ['queue' => 'priority']]],
        'efatura'     => ['emitter' => null, 'queue' => ['connection' => null, 'queue' => null], 'http' => ['middleware' => ['timeout_seconds' => null]]],
    ]);

    expect((new LoadEfaturaConfig($repository))()->queue->queue)->toBeNull();
});

it('observes repository overrides made before each load', function (): void {
    $repository = resolve('config');
    $repository->set('efatura.environment', 'PRODUCTION');
    $repository->set('efatura.transmitter.tax_id', '100200300');

    $loader   = new LoadEfaturaConfig($repository);
    $original = $loader();
    $repository->set('efatura.environment', 'TEST');

    expect($original->environment->repositoryCode())->toBe(1)
        ->and($loader()->environment->repositoryCode())->toBe(3)
        ->and($original->emitter)->toBeNull();
});

it('keeps resolved configuration graphs immutable', function (): void {
    $config = ConfigFixtures::load();

    expect(function () use ($config): void {
        $config->http->defaults->timeoutSeconds = 99;
    })->toThrow(Error::class, 'Cannot modify readonly property ' . HttpClientConfig::class . '::$timeoutSeconds');
});

it('maps the configured emitter onto the fiscal party payload field by field', function (): void {
    $emitter = ConfigFixtures::load(['emitter' => [
        'tax_id'   => '100200300', 'name' => 'Fiscal party',
        'address'  => ['country_code' => 'CV', 'address_detail' => 'Praia office', 'building_floor' => '2'],
        'contacts' => ['email' => 'fiscal@example.test', 'mobile' => '9900000'],
    ]])->emitter;

    expect($emitter->partyPayload())->toBe([
        'taxId'    => ['value' => '100200300', 'countryCode' => 'CV'],
        'name'     => 'Fiscal party',
        'address'  => ['countryCode' => 'CV', 'addressDetail' => 'Praia office', 'buildingFloor' => '2'],
        'contacts' => ['email' => 'fiscal@example.test', 'telephone' => null, 'mobilephone' => '9900000', 'telefax' => null, 'website' => null],
    ]);
});

it('leaves the address out of a configured emitter that has none', function (): void {
    $emitter = ConfigFixtures::load(['emitter' => ['contacts' => ['email' => 'fiscal@example.test']]])->emitter;

    expect($emitter->partyPayload()['address'])->toBeNull()
        ->and($emitter->taxIdPayload())->toBe(['value' => null, 'countryCode' => 'CV']);
});
