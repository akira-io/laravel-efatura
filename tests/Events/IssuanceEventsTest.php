<?php

declare(strict_types=1);

use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Actions\PrepareEventAction;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Events\DocumentAuthorized;
use Akira\Efatura\Events\DocumentPrepared;
use Akira\Efatura\Events\DocumentRejected;
use Akira\Efatura\Events\DocumentSubmissionFailed;
use Akira\Efatura\Events\EventAuthorized;
use Akira\Efatura\Events\EventPrepared;
use Akira\Efatura\Events\EventRejected;
use Akira\Efatura\Events\EventSubmissionFailed;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Transport\EntryResult;

it('keeps every secret out of the issuance events', function (): void {
    $key = 'mwkey-' . bin2hex(random_bytes(29));
    P::configure('pem-encrypted', config: ['efatura.transmitter.middleware_key' => $key, 'efatura.transmitter.oauth.client_secret' => 'client-secret-7f3a']);
    $document = resolve(PrepareDocumentAction::class)->handle(P::invoice());
    $event    = resolve(PrepareEventAction::class)->handle(P::event());
    $result   = new EntryResult($document->iud . '.xml', $document->iud, true, 'AUTH-1', null, []);
    $events   = [
        new DocumentPrepared($document),
        new DocumentAuthorized($document, $result),
        new DocumentRejected($document, $result),
        new DocumentSubmissionFailed($document, TransportFailure::OutcomeUnknown->value, false, true),
        new EventPrepared($event),
        new EventAuthorized($event, $result),
        new EventRejected($event, $result),
        new EventSubmissionFailed($event, TransportFailure::ConnectionFailed->value, true, false),
    ];
    $exposed = collect($events)->map(fn (object $announced): string => json_encode($announced, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . serialize($announced))->implode('');

    expect($exposed)->toContain($document->iud)->toContain($event->eventId)
        ->not->toContain($key)
        ->not->toContain('client-secret-7f3a')
        ->not->toContain(trim(C::PASSPHRASE))
        ->not->toContain('PRIVATE KEY');
});
