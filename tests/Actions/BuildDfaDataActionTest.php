<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDfaDataAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Dfa\DfaContent;
use Akira\Efatura\Dfa\DfaData;
use Akira\Efatura\Dfa\DfaLine;
use Akira\Efatura\Dfa\DfaParty;
use Akira\Efatura\Dfa\DfaTotals;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\DfaException;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Tests\Support\DfaFixtures as D;
use Carbon\CarbonImmutable;

function buildDfa(IssuedDocument|PreparedDocument $source, ?string $iuc = null): DfaData
{
    return resolve(BuildDfaDataAction::class)->handle($source, $iuc);
}

function dfaFailure(Closure $operation): DfaException
{
    try {
        $operation();
    } catch (DfaException $dfaException) {
        return $dfaException;
    }

    throw new RuntimeException('The DFA was built.');
}

it('builds the ONLINE DFA of an authorized document with its QR code and authorization code', function (): void {
    $iud = D::iud();

    expect(buildDfa(D::issued()))->toEqual(new DfaData(
        EmissionMode::Online,
        $iud,
        'https://pe.efatura.cv/dfe/view/' . $iud,
        'AUTH-77',
        null,
        new DfaContent(
            new DfaParty('100200300', 'Emitter', 'Praia office', '1234567'),
            new DfaParty('900800700', 'Receiver', null, null),
            'FTE',
            'A',
            '7',
            CarbonImmutable::parse('2026-10-02 12:00:00', 'Atlantic/Cape_Verde'),
            [new DfaLine('SKU', 'Product', '1', 'C62', '100$00', '15%', '100$00')],
            new DfaTotals(1, '100$00', '0$00', '0$00', '15$00', null, null, '115$00'),
            [],
        ),
    ));
});

it('builds the same DFA every time the same document is printed', function (): void {
    $issued = D::issued();

    expect(buildDfa($issued))->toEqual(buildDfa($issued))
        ->and(buildDfa(D::prepared(D::offline())))->toEqual(buildDfa(D::prepared(D::offline())));
});

it('prints the authorized model for an authorized contingency document', function (): void {
    $dfa = buildDfa(D::issued(D::prepared(D::off())));

    expect($dfa->mode)->toBe(EmissionMode::Online)
        ->and($dfa->pendingAuthorization())->toBeFalse()
        ->and($dfa->qrPayload)->toBe('https://pe.efatura.cv/dfe/view/' . D::iud())
        ->and($dfa->iuc)->toBeNull();
});

it('builds the OFFLINE DFA of a prepared contingency document with its QR code and customer', function (): void {
    $dfa = buildDfa(D::prepared(D::offline()));

    expect($dfa->mode)->toBe(EmissionMode::Offline)
        ->and($dfa->pendingAuthorization())->toBeTrue()
        ->and($dfa->showsItemCodes())->toBeTrue()
        ->and($dfa->qrPayload)->toBe('https://pe.efatura.cv/dfe/view/' . D::iud())
        ->and($dfa->authorizationCode)->toBeNull()
        ->and($dfa->iuc)->toBeNull()
        ->and($dfa->content->receiver)->toEqual(new DfaParty('900800700', 'Receiver', null, null))
        ->and($dfa->filename())->toBe(D::iud() . '.pdf');
});

it('builds the OFF DFA with the IUC, without QR code, item codes or customer', function (?string $given): void {
    $dfa = buildDfa(D::prepared(D::off()), $given);

    expect($dfa->mode)->toBe(EmissionMode::Off)
        ->and($dfa->pendingAuthorization())->toBeTrue()
        ->and($dfa->showsItemCodes())->toBeFalse()
        ->and($dfa->qrPayload)->toBeNull()
        ->and($dfa->iuc)->toBe('2026/15')
        ->and($dfa->content->receiver)->toBeNull();
})->with([
    'recorded on the document' => [null],
    'given and recorded alike' => ['2026/15'],
]);

it('reads the IUC from the caller when the document carries none', function (): void {
    expect(buildDfa(D::prepared(D::offWithoutIuc()), '2026/42')->iuc)->toBe('2026/42');
});

it('appends the IUD to the configured viewer address', function (): void {
    config()->set('efatura.dfa.base_url', 'https://viewer.example.cv/dfe/');
    app()->forgetInstance(EfaturaConfig::class);

    expect(buildDfa(D::issued())->qrPayload)->toBe('https://viewer.example.cv/dfe/' . D::iud());
});

it('refuses a DFA for a document the platform has not authorized', function (Closure $source): void {
    $exception = dfaFailure(fn (): DfaData => buildDfa($source()));

    expect($exception->errorCode)->toBe('dfa.not_authorized')
        ->and($exception->context)->toBe(['iud' => D::iud()]);
})->with([
    'rejected'                  => [fn (): IssuedDocument => D::issued(authorized: false)],
    'prepared without emission' => [fn (): PreparedDocument => D::prepared()],
    'prepared in online mode'   => [fn (): PreparedDocument => D::prepared(D::invoice(['emission' => ['issueMode' => 1]]))],
]);

it('refuses an OFF DFA without a valid and consistent IUC', function (Closure $source, ?string $given, string $errorCode): void {
    expect(dfaFailure(fn (): DfaData => buildDfa($source(), $given))->errorCode)->toBe($errorCode);
})->with([
    'missing'        => [fn (): PreparedDocument => D::prepared(D::offWithoutIuc()), null, 'dfa.iuc_required'],
    'malformed'      => [fn (): PreparedDocument => D::prepared(D::offWithoutIuc()), '15', 'dfa.invalid_iuc'],
    'another number' => [fn (): PreparedDocument => D::prepared(D::off()), '2026/16', 'dfa.iuc_mismatch'],
]);

it('refuses a document whose IUD is not official', function (): void {
    $exception = dfaFailure(fn (): DfaData => buildDfa(D::prepared(D::offline(), 'CV0000')));

    expect($exception)->toBeInstanceOf(EfaturaException::class)
        ->and($exception->errorCode)->toBe('dfa.invalid_iud')
        ->and($exception->field)->toBe('iud')
        ->and($exception->getMessage())->toBe('dfa.invalid_iud');
});
