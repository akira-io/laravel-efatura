<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Tests\Support\SelfBillingFixtures as S;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SelfBillingAuthorization;
use Akira\Efatura\Transport\SelfBillingResponseParser;

function parseSelfBilling(string $body, int $status = 200, Environment $repository = Environment::Test): SelfBillingAuthorization
{
    return resolve(SelfBillingResponseParser::class)->parse(S::request(), $repository, $status, $body);
}

it('reads the authorization the platform grants', function (): void {
    expect(parseSelfBilling(S::granted()))->toEqual(new SelfBillingAuthorization(S::AUTHORIZATION_ID, 3600, S::iud(), 'AUTOFATURA', 7, 42));
});

it('ignores fields the platform adds to an authorization', function (): void {
    $body = (string) json_encode(['succeeded' => true, 'payload' => [...S::payload(), 'issuedAt' => 'later'], 'messages' => []]);

    expect(parseSelfBilling($body)->iud)->toBe(S::iud());
});

it('turns a refusal into a rejection that carries the messages of the platform', function (int $status): void {
    expect(fn (): SelfBillingAuthorization => parseSelfBilling(S::refused(), $status))->toThrow(function (TransportException $exception) use ($status): void {
        expect($exception->failure)->toBe(TransportFailure::Rejected)
            ->and($exception->getMessage())->toBe('transport.rejected')
            ->and($exception->retryable)->toBeFalse()
            ->and($exception->context)->toBe(['status' => $status])
            ->and($exception->messages)->toEqual([
                new RemoteMessage('BIZ', 'ERROR', '2026-02-08 09:15:30', 'BIZ-901', 'Seller is not a micro company.', null),
                new RemoteMessage('BIZ', 'WARNING', null, 'BIZ-902', null, 'mobilePhoneNumber'),
            ]);
    });
})->with([200, 422]);

it('fails closed on anything but the documented answer', function (string $body): void {
    expect(fn (): SelfBillingAuthorization => parseSelfBilling($body))->toThrow(function (TransportException $exception) use ($body): void {
        expect($exception->failure)->toBe(TransportFailure::UnexpectedResponse)
            ->and($exception->messages)->toBe([])
            ->and($exception->context)->toBe(['status' => 200, 'bytes' => strlen($body)])
            ->and($exception->getMessage())->toBe('transport.unexpected_response');
    });
})->with([
    'an empty body'                => [fn (): string => ''],
    'proxy html'                   => [fn (): string => '<html>hostile</html>'],
    'truncated json'               => [fn (): string => substr(S::granted(), 0, -2)],
    'a json scalar'                => [fn (): string => 'true'],
    'no payload'                   => [fn (): string => (string) json_encode(['succeeded' => true])],
    'a payload list'               => [fn (): string => (string) json_encode(['succeeded' => true, 'payload' => [1, 2]])],
    'an authorization id no uuid'  => [fn (): string => S::granted(['authorizationId' => 'not-a-uuid'])],
    'no expiration'                => [fn (): string => S::granted(['authorizationCodeExpirationSeconds' => 0])],
    'succeeded as other text'      => [fn (): string => (string) json_encode(['succeeded' => 'yes', 'payload' => S::payload()])],
    'succeeded as a number'        => [fn (): string => (string) json_encode(['succeeded' => 1, 'payload' => S::payload()])],
    'a signed text led'            => [fn (): string => S::granted(['ledCode' => '+7'])],
    'a decimal text number'        => [fn (): string => S::granted(['documentNumber' => '42.0'])],
    'a fractional expiration'      => [fn (): string => S::granted(['authorizationCodeExpirationSeconds' => 3600.5])],
    'an overlong text number'      => [fn (): string => S::granted(['documentNumber' => str_repeat('9', 19)])],
    'a malformed iud'              => [fn (): string => S::granted(['iud' => 'CV3'])],
    'an iud of another seller'     => [fn (): string => S::granted(['iud' => S::iud(['emitterTaxId' => '300400500'])])],
    'an iud of another type'       => [fn (): string => S::granted(['iud' => S::iud(['documentTypeCode' => 'FRE'])])],
    'an iud of another led'        => [fn (): string => S::granted(['ledCode' => 8])],
    'an iud of another number'     => [fn (): string => S::granted(['documentNumber' => 43])],
    'an iud of another repository' => [fn (): string => S::granted(['iud' => S::iud(['repositoryCode' => 2])])],
    'a malformed series'           => [fn (): string => S::granted(['serie' => 'AUTO FATURA'])],
    'a refusal without messages'   => [fn (): string => (string) json_encode(['succeeded' => false])],
    'a refusal with no message'    => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => []])],
    'a message map'                => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => ['first' => ['source' => 'B', 'type' => 'E', 'code' => 'C']]])],
    'a message without code'       => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => [['source' => 'BIZ', 'type' => 'ERROR']]])],
    'a message of unknown fields'  => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => [['source' => 'B', 'type' => 'E', 'code' => 'C', 'hint' => 'x']]])],
    'a message field twice'        => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => [['source' => 'B', 'type' => 'E', 'code' => 'C', 'Code' => 'D']]])],
    'a numeric message field'      => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => [['source' => 'B', 'type' => 'E', 'code' => 7]]])],
    'a blank message code'         => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => [['source' => 'B', 'type' => 'E', 'code' => ' ']]])],
    'a message that is text'       => [fn (): string => (string) json_encode(['succeeded' => false, 'messages' => ['refused']])],
    'invalid utf-8'                => [fn (): string => "{\"succeeded\":false,\"messages\":[{\"code\":\"\xC3\x28\"}]}"],
    'nesting beyond the limit'     => [fn (): string => str_repeat('[', 20) . str_repeat(']', 20)],
]);

it('reads integers and the success flag sent as text, since figure 16 sets no types', function (): void {
    $payload = [...S::payload(), 'authorizationCodeExpirationSeconds' => '3600', 'ledCode' => '7', 'documentNumber' => '42'];

    expect(parseSelfBilling((string) json_encode(['succeeded' => 'true', 'payload' => $payload])))
        ->toEqual(new SelfBillingAuthorization(S::AUTHORIZATION_ID, 3600, S::iud(), 'AUTOFATURA', 7, 42))
        ->and(fn (): SelfBillingAuthorization => parseSelfBilling((string) json_encode(['succeeded' => 'false', 'messages' => [['source' => 'B', 'type' => 'E', 'code' => 'C']]])))
        ->toThrow(TransportException::class, 'transport.rejected');
});

it('reads the authorization only for the repository it was asked in', function (): void {
    expect(fn (): SelfBillingAuthorization => parseSelfBilling(S::granted(), repository: Environment::Homologation))
        ->toThrow(TransportException::class, 'transport.unexpected_response');
});
