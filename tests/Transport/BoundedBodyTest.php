<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\BoundedBody;
use Akira\Efatura\Transport\TransportRequests;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

function boundedResponse(string $body, array $headers = []): Response
{
    Http::fake(['*' => Http::response($body, 200, $headers)]);

    return resolve(TransportRequests::class)->for(ConfigFixtures::load()->http->middleware, Environment::Test)->get('dfe');
}

it('reads a body of exactly the response limit', function (): void {
    $body = str_repeat('a', Fiscal::MAX_RESPONSE_BYTES);

    expect(resolve(BoundedBody::class)->read(boundedResponse($body), T::archive()))->toBe($body);
});

it('reads an empty body', function (): void {
    expect(resolve(BoundedBody::class)->read(boundedResponse(''), T::FIRST_IUD))->toBe('');
});

it('refuses one byte above the response limit without echoing the body', function (array $headers): void {
    $body = str_repeat('b', Fiscal::MAX_RESPONSE_BYTES) . 'hostile-marker';

    expect(fn (): string => resolve(BoundedBody::class)->read(boundedResponse($body, $headers), T::archive()))
        ->toThrow(function (TransportException $exception): void {
            expect($exception->failure)->toBe(TransportFailure::ResponseTooLarge)
                ->and($exception->context)->toBe(['status' => 200, 'kind' => 'dfe', 'identifiers' => T::FIRST_IUD, 'bytes' => Fiscal::MAX_RESPONSE_BYTES + 1])
                ->and($exception->getMessage())->toBe('transport.response_too_large');
        });
})->with([
    'no content length'      => [[]],
    'a lying content length' => [['Content-Length' => '10']],
]);

it('reads a short body whatever content length it claims', function (): void {
    expect(resolve(BoundedBody::class)->read(boundedResponse('<Responses/>', ['Content-Length' => (string) (Fiscal::MAX_RESPONSE_BYTES * 2)]), T::archive()))
        ->toBe('<Responses/>');
});

it('reads the body from the start after something consumed it', function (): void {
    $response = boundedResponse('<Responses/>');
    $response->toPsrResponse()->getBody()->read(5);

    expect(resolve(BoundedBody::class)->read($response, T::archive()))->toBe('<Responses/>');
});

it('reads a body of exactly a smaller limit when asked', function (): void {
    expect(resolve(BoundedBody::class)->read(boundedResponse('abcd'), 'bcv', 4))->toBe('abcd');
});

it('refuses one byte above a smaller limit when asked', function (): void {
    expect(fn (): string => resolve(BoundedBody::class)->read(boundedResponse('abcde'), 'bcv', 4))
        ->toThrow(function (TransportException $exception): void {
            expect($exception->context)->toBe(['status' => 200, 'identifiers' => 'bcv', 'bytes' => 5]);
        });
});
