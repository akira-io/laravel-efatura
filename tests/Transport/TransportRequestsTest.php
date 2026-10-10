<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\TransportRequests;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->bundle   = (string) tempnam(sys_get_temp_dir(), 'efatura-ca-');
    $this->requests = resolve(TransportRequests::class);
});

afterEach(function (): void {
    unlink($this->bundle);
});

it('bounds every request by the timeouts and trust of its client profile', function (): void {
    $http    = ConfigFixtures::load(['http' => ['timeout_seconds' => 40, 'middleware' => ['connect_timeout_seconds' => 4, 'ca_bundle_path' => $this->bundle]]])->http;
    $options = $this->requests->for($http->middleware, T::archive())->getOptions();

    expect($options['timeout'])->toBe(40)
        ->and($options['connect_timeout'])->toBe(4)
        ->and($options['verify'])->toBe($this->bundle)
        ->and($options['allow_redirects'])->toBeFalse()
        ->and($options['stream'])->toBeTrue()
        ->and($this->requests->for($http->platform, Environment::Test)->getOptions()['verify'])->toBeTrue();
});

it('sends the repository of the archive and accepts xml from the configured base url', function (): void {
    Http::fake(['*' => Http::response('<Responses/>')]);

    $this->requests->for(ConfigFixtures::load()->http->middleware, T::archive(repository: Environment::Homologation))->get('dfe');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://localhost:3443/v1/dfe'
        && $request->header('cv-ef-repository-code') === ['2']
        && $request->header('Accept') === ['application/xml']);
});

it('starts every request without the headers of the previous one', function (): void {
    Http::fake(['*' => Http::response('<Responses/>')]);
    $platform = ConfigFixtures::load()->http->platform;

    $this->requests->for($platform, Environment::Test)->withToken('first-emitter-token')->get('dfe');
    $this->requests->for($platform, Environment::Production)->get('dfe');

    $recorded = Http::recorded()->map(fn (array $pair): Request => $pair[0])->values();

    expect($recorded)->toHaveCount(2)
        ->and($recorded[0]->header('Authorization'))->toBe(['Bearer first-emitter-token'])
        ->and($recorded[1]->hasHeader('Authorization'))->toBeFalse()
        ->and($recorded[1]->header('cv-ef-repository-code'))->toBe(['1']);
});

it('does not follow a redirect', function (): void {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.example.test/dfe'])]);

    $response = $this->requests->for(ConfigFixtures::load()->http->platform, Environment::Test)->get('dfe');

    expect($response->status())->toBe(302);
    Http::assertSentCount(1);
});

it('refuses a client profile without a base url', function (): void {
    expect(fn (): PendingRequest => $this->requests->for(ConfigFixtures::load()->http->bcv, Environment::Test))
        ->toThrow(DefinitionException::class, 'A fiscal request needs an HTTP client profile with a base URL.');
});
