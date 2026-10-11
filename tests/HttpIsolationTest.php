<?php

declare(strict_types=1);

use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('refuses any request the test did not fake', function (): void {
    expect(fn (): Response => Http::get('https://example.test'))->toThrow(StrayRequestException::class);
});

it('serves the requests a test fakes', function (): void {
    Http::fake(['example.test/*' => Http::response('faked')]);

    expect(Http::get('https://example.test/resource')->body())->toBe('faked');
});
