<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\SelfBillingFixtures as S;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\MiddlewareTransport;
use Illuminate\Support\Facades\Http;

function exchangeFailure(Closure $send): TransportException
{
    try {
        $send(resolve(MiddlewareTransport::class));
    } catch (TransportException $transportException) {
        return $transportException;
    }

    throw new RuntimeException('The exchange did not fail.');
}

beforeEach(function (): void {
    app()->instance(EfaturaConfig::class, ConfigFixtures::load([
        'transmitter' => ['middleware_key' => str_repeat('k', Fiscal::TRANSMITTER_KEY_LENGTH)],
        'http'        => ['retry_delay_milliseconds' => 0],
    ]));
});

it('maps the status of an error answer before refusing its oversized body', function (int $status, TransportFailure $failure, Closure $send): void {
    Http::fake(['*' => Http::response(str_repeat('a', Fiscal::MAX_RESPONSE_BYTES + 1), $status)]);

    expect(exchangeFailure($send)->failure)->toBe($failure);
})->with([
    'payload too large' => [413, TransportFailure::PayloadTooLarge],
    'server error'      => [500, TransportFailure::OutcomeUnknown],
    'success'           => [200, TransportFailure::ResponseTooLarge],
])->with([
    'a submission'           => [fn (MiddlewareTransport $transport): mixed => $transport->submit(T::archive())],
    'a self-billing request' => [fn (MiddlewareTransport $transport): mixed => $transport->authorizeSelfBilling(S::request())],
]);

it('reports a partial answer to a lookup as unexpected', function (): void {
    Http::fake(['*' => Http::response('<Dfe xmlns="' . Fiscal::XML_NAMESPACE . '" Id="' . T::FIRST_IUD . '"/>', 206)]);

    $exception = exchangeFailure(fn (MiddlewareTransport $transport): mixed => $transport->lookupDocument(T::FIRST_IUD));

    expect($exception->failure)->toBe(TransportFailure::UnexpectedResponse)
        ->and($exception->context['status'])->toBe(206);
});

it('repeats a lookup only after the statuses a read may retry', function (int $status, int $requests, bool $found): void {
    $dfe = '<Dfe xmlns="' . Fiscal::XML_NAMESPACE . '" Id="' . T::FIRST_IUD . '"/>';
    Http::fake(['*' => Http::sequence()->push('', $status)->push($dfe, 200)]);

    try {
        $lookup = resolve(MiddlewareTransport::class)->lookupDocument(T::FIRST_IUD)->found;
    } catch (TransportException) {
        $lookup = false;
    }

    expect($lookup)->toBe($found);
    Http::assertSentCount($requests);
})->with([
    'too many requests'     => [429, 2, true],
    'bad gateway'           => [502, 2, true],
    'service unavailable'   => [503, 2, true],
    'gateway timeout'       => [504, 2, true],
    'internal server error' => [500, 1, false],
    'not implemented'       => [501, 1, false],
]);
