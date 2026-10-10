<?php

declare(strict_types=1);

use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SubmissionOutcome;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Akira\Efatura\Tests\Support\IdentifierFixtures;
use Akira\Efatura\Tests\Support\TransportFixtures as T;
use Akira\Efatura\Transport\RemoteMessage;
use Akira\Efatura\Transport\SubmissionResponseParser;
use Akira\Efatura\Transport\SubmissionResult;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-08 10:16:00');
    $this->parser = resolve(SubmissionResponseParser::class);
});

it('reads an accepted entry with its authorization code and signature timestamp', function (): void {
    $result = $this->parser->parse(T::archive(), 200, T::response('accepted'));
    $entry  = $result->entry(T::FIRST_IUD);

    expect($result->outcome())->toBe(SubmissionOutcome::Accepted)
        ->and($result->kind)->toBe(PackageKind::Documents)
        ->and($result->status)->toBe(200)
        ->and($result->receivedAt->equalTo(CarbonImmutable::now()))->toBeTrue()
        ->and($entry->entryName)->toBe(T::FIRST_IUD . '.xml')
        ->and($entry->succeeded)->toBeTrue()
        ->and($entry->authorizationCode)->toBe('AUTH-0001')
        ->and($entry->signatureTimeStamp)->toBe('2026-02-08 10:15:30')
        ->and($entry->messages)->toBe([]);
});

it('keeps every message of a rejected entry in order and as received', function (): void {
    $result = $this->parser->parse(T::archive(), 200, T::response('rejected'));
    $entry  = $result->entry(T::FIRST_IUD);

    expect($result->outcome())->toBe(SubmissionOutcome::Rejected)
        ->and($entry->succeeded)->toBeFalse()
        ->and($entry->authorizationCode)->toBeNull()
        ->and($entry->signatureTimeStamp)->toBeNull()
        ->and($entry->messages)->toEqual([
            new RemoteMessage('BIZ', 'ERROR', '2026-02-08 10:15:30', 'BIZ-001', 'The emitter is not registered.', '/Dfe/EmitterParty/TaxId'),
            new RemoteMessage('XSD', 'WARNING', null, 'XSD-014', null, null),
        ]);
});

it('reports each entry of a partial acceptance in the order of the archive', function (): void {
    $result = $this->parser->parse(T::archive([T::FIRST_IUD, T::SECOND_IUD, T::THIRD_IUD]), 200, T::response('partial'));

    expect($result->outcome())->toBe(SubmissionOutcome::Partial)
        ->and(array_map(fn ($entry): string => $entry->identifier, $result->entries))->toBe([T::FIRST_IUD, T::SECOND_IUD, T::THIRD_IUD])
        ->and(array_map(fn ($entry): bool => $entry->succeeded, $result->entries))->toBe([true, false, true])
        ->and($result->entry(T::THIRD_IUD)->authorizationCode)->toBe('AUTH-0003')
        ->and($result->entry(T::SECOND_IUD)->messages[0]->code)->toBe('BIZ-002')
        ->and($result->entry(T::SECOND_IUD)->messages[0]->location)->toBeNull();
});

it('reads the response to an event archive under the event identifier', function (): void {
    $body   = str_replace(T::FIRST_IUD, IdentifierFixtures::OFFICIAL_EVENT_ID, T::response('accepted'));
    $result = $this->parser->parse(T::archive([IdentifierFixtures::OFFICIAL_EVENT_ID], PackageKind::Events), 200, $body);

    expect($result->kind)->toBe(PackageKind::Events)
        ->and($result->entry(IdentifierFixtures::OFFICIAL_EVENT_ID)->authorizationCode)->toBe('AUTH-0001');
});

it('never turns a hostile or incomplete body into a result', function (Closure $body, array $identifiers): void {
    $archive  = T::archive($identifiers);
    $hostile  = $body(T::response('accepted'));
    $original = ini_set('zend.exception_ignore_args', '0');

    try {
        expect(fn (): SubmissionResult => $this->parser->parse($archive, 502, $hostile))->toThrow(function (TransportException $exception) use ($hostile, $archive): void {
            $exposed = $exception->getMessage() . print_r($exception->context, true) . print_r(ExceptionTrace::packageArguments($exception), true);

            expect($exception->failure)->toBe(TransportFailure::UnexpectedResponse)
                ->and($exception->getMessage())->toBe('transport.unexpected_response')
                ->and($exception->retryable)->toBeFalse()
                ->and($exception->getPrevious())->toBeNull()
                ->and($exception->context)->toBe(['status' => 502, 'kind' => 'dfe', 'identifiers' => implode(',', $archive->identifiers()), 'bytes' => strlen($hostile)])
                ->and($exposed)->not->toContain('hostile-marker');

            if ($hostile !== '') {
                expect($exposed)->not->toContain($hostile);
            }
        });
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }
})->with([
    'empty body'              => [fn (string $accepted): string => '', [T::FIRST_IUD]],
    'proxy html'              => [fn (string $accepted): string => '<html><body><h1>Bad gateway hostile-marker</h1></body></html>', [T::FIRST_IUD]],
    'json'                    => [fn (string $accepted): string => '{"succeeded":true,"note":"hostile-marker"}', [T::FIRST_IUD]],
    'doctype with entity'     => [fn (string $accepted): string => '<?xml version="1.0"?><!DOCTYPE Responses [<!ENTITY x "hostile-marker">]><Responses>&x;</Responses>', [T::FIRST_IUD]],
    'malformed xml'           => [fn (string $accepted): string => '<Responses><Response Succeeded="true">hostile-marker', [T::FIRST_IUD]],
    'invalid utf-8'           => [fn (string $accepted): string => str_replace('AUTH-0001', "AUTH-\xC3\x28 hostile-marker", $accepted), [T::FIRST_IUD]],
    'namespaced root'         => [fn (string $accepted): string => str_replace('<Responses>', '<Responses xmlns="urn:hostile-marker">', $accepted), [T::FIRST_IUD]],
    'other root'              => [fn (string $accepted): string => str_replace('Responses>', 'Results>', $accepted), [T::FIRST_IUD]],
    'succeeded yes'           => [fn (string $accepted): string => str_replace('Succeeded="true"', 'Succeeded="yes"', $accepted), [T::FIRST_IUD]],
    'succeeded missing'       => [fn (string $accepted): string => str_replace(' Succeeded="true"', '', $accepted), [T::FIRST_IUD]],
    'entry name missing'      => [fn (string $accepted): string => preg_replace('#<EntryName>.*</EntryName>#', '', $accepted), [T::FIRST_IUD]],
    'entry not in the zip'    => [fn (string $accepted): string => str_replace(T::FIRST_IUD, T::SECOND_IUD, $accepted), [T::FIRST_IUD]],
    'entry without response'  => [fn (string $accepted): string => $accepted, [T::FIRST_IUD, T::SECOND_IUD]],
    'duplicate response'      => [fn (string $accepted): string => preg_replace('#(<Response .*</Response>)#s', '$1$1', $accepted), [T::FIRST_IUD]],
    'success without code'    => [fn (string $accepted): string => preg_replace('#<AuthorizationCode>.*</AuthorizationCode>#', '', $accepted), [T::FIRST_IUD]],
    'failure with code'       => [fn (string $accepted): string => str_replace('Succeeded="true"', 'Succeeded="false"', $accepted), [T::FIRST_IUD]],
    'failure without message' => [fn (string $accepted): string => preg_replace('#<Messages>.*</Messages>#s', '', T::response('rejected')), [T::FIRST_IUD]],
    'empty messages'          => [fn (string $accepted): string => preg_replace('#<Messages>.*</Messages>#s', '<Messages/>', T::response('rejected')), [T::FIRST_IUD]],
    'message without code'    => [fn (string $accepted): string => str_replace('<Code>XSD-014</Code>', '', T::response('rejected')), [T::FIRST_IUD]],
    'text among messages'     => [fn (string $accepted): string => str_replace('<Messages>', '<Messages>hostile-marker', T::response('rejected')), [T::FIRST_IUD]],
    'unknown response field'  => [fn (string $accepted): string => str_replace('<AuthorizationCode>', '<Status>ok</Status><AuthorizationCode>', $accepted), [T::FIRST_IUD]],
    'repeated field'          => [fn (string $accepted): string => str_replace('<AuthorizationCode>', '<AuthorizationCode>X</AuthorizationCode><AuthorizationCode>', $accepted), [T::FIRST_IUD]],
    'nested field value'      => [fn (string $accepted): string => str_replace('AUTH-0001', '<b>hostile-marker</b>', $accepted), [T::FIRST_IUD]],
    'comment among responses' => [fn (string $accepted): string => str_replace('<Response ', '<!-- hostile-marker --><Response ', $accepted), [T::FIRST_IUD]],
    'no responses'            => [fn (string $accepted): string => '<Responses/>', [T::FIRST_IUD]],
]);

it('refuses a body above the response limit without reading it', function (): void {
    $body = str_repeat(' ', Fiscal::MAX_RESPONSE_BYTES + 1);

    expect(fn (): SubmissionResult => $this->parser->parse(T::archive(), 200, $body))->toThrow(function (TransportException $exception): void {
        expect($exception->failure)->toBe(TransportFailure::ResponseTooLarge)
            ->and($exception->context['bytes'])->toBe(Fiscal::MAX_RESPONSE_BYTES + 1);
    });
});

it('reads a body of exactly the response limit', function (): void {
    $accepted = T::response('accepted');
    $body     = $accepted . str_repeat(' ', Fiscal::MAX_RESPONSE_BYTES - strlen($accepted));

    expect($this->parser->parse(T::archive(), 200, $body)->outcome())->toBe(SubmissionOutcome::Accepted);
});
