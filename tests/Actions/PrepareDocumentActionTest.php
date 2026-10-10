<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildIudAction;
use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\CertificateException;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Exceptions\PreparationException;
use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Sequence\InMemorySequenceStore;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\PackageFixtures;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\SignatureVerifier as V;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

beforeEach(function (): void {
    $this->store     = P::configure();
    $this->directory = sys_get_temp_dir() . '/efatura-preparation-' . Str::uuid()->toString();
    new Filesystem()->ensureDirectoryExists($this->directory);
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->directory);
});

it('numbers, writes, validates, signs and packages a document without sending it', function (SignatureProfile $profile): void {
    $prepared = resolve(PrepareDocumentAction::class)->handle(P::invoice(), profile: $profile);
    $parsed   = resolve(ParseIudAction::class)->handle($prepared->iud);
    $entries  = PackageFixtures::entries($prepared->archive->bytes, $this->directory);
    $xpath    = V::xpath(S::document($prepared->unsignedXml));

    V::verify($prepared->signed->xml);

    expect($prepared->allocated)->toBeTrue()
        ->and($prepared->document->header->documentNumber)->toBe(1)
        ->and($parsed->documentNumber)->toBe(1)
        ->and($parsed->repository)->toBe(Environment::Test)
        ->and($parsed->emitterTaxId)->toBe('100200300')
        ->and($prepared->document->emission?->transmitterTaxId?->value)->toBe(P::TRANSMITTER)
        ->and(fn () => resolve(SchemaValidator::class)->validate($prepared->unsignedXml))->not->toThrow(Throwable::class)
        ->and($xpath->query('//*[local-name() = "IsSpecimen"]')?->length)->toBe(0)
        ->and($prepared->signed->id)->toBe($prepared->iud)
        ->and($prepared->signed->profile)->toBe($profile)
        ->and($prepared->archive->kind)->toBe(PackageKind::Documents)
        ->and($prepared->archive->entries)->toBe([$prepared->iud . '.xml'])
        ->and(array_column($entries, 'contents'))->toBe([$prepared->signed->xml])
        ->and($this->store->current(SequenceFixtures::scope()))->toBe(1);
})->with(SignatureProfile::cases());

it('writes a specimen when asked to', function (): void {
    $prepared = resolve(PrepareDocumentAction::class)->handle(P::invoice(), isSpecimen: true);

    expect(V::xpath(S::document($prepared->unsignedXml))->query('//*[local-name() = "IsSpecimen"]')?->item(0)?->textContent)->toBe('true');
});

it('loads the credentials before it reserves a number', function (Closure $configure, string $exception): void {
    $configure();

    expect(fn (): PreparedDocument => resolve(PrepareDocumentAction::class)->handle(P::invoice()))->toThrow($exception)
        ->and(resolve(SequenceStore::class)->current(SequenceFixtures::scope()))->toBe(0);
})->with([
    'a missing transmitter'  => [fn (): InMemorySequenceStore => P::configure(config: ['efatura.transmitter.tax_id' => null]), ConfigurationException::class],
    'an expired certificate' => [fn (): InMemorySequenceStore => P::configure(certificate: P::expired()), CertificateException::class],
]);

it('reports the consumed number and iud when preparation fails after the reservation', function (): void {
    $original = ini_set('zend.exception_ignore_args', '0');
    $store    = P::configure('pem-encrypted');
    $secrets  = [C::PASSPHRASE, trim(C::PASSPHRASE), C::signer()->keyPem(), C::signer()->keyPem(C::PASSPHRASE)];

    try {
        expect(fn (): PreparedDocument => resolve(PrepareDocumentAction::class)->handle(P::invoice(header: [])))
            ->toThrow(function (PreparationException $exception) use ($secrets): void {
                $previous = $exception->getPrevious();
                $dumps    = [
                    $exception->getMessage(),
                    var_export($exception->context, true),
                    (string) json_encode($exception),
                    print_r(P::packageArguments($exception), true),
                    (string) $previous?->getMessage(),
                ];

                expect(P::packageArguments($exception))->not->toBeEmpty()
                    ->and($exception->errorCode)->toBe('preparation.failed_after_allocation')
                    ->and($exception->getMessage())->toBe('preparation.failed_after_allocation')
                    ->and($previous)->toBeInstanceOf(ValidationException::class)
                    ->and(array_keys($previous instanceof ValidationException ? $previous->errors() : []))->toBe(['header.serie'])
                    ->and(array_keys($exception->context))->toBe(['documentNumber', 'iud', 'emitterTaxId', 'fiscalYear', 'ledCode', 'documentTypeCode'])
                    ->and($exception->context['documentNumber'])->toBe(1)
                    ->and(resolve(ParseIudAction::class)->handle((string) $exception->context['iud'])->documentNumber)->toBe(1)
                    ->and(array_slice($exception->context, 2))
                    ->toBe(['emitterTaxId' => '100200300', 'fiscalYear' => 2026, 'ledCode' => 1, 'documentTypeCode' => 1]);

                foreach ($secrets as $secret) {
                    expect(implode("\n", $dumps))->not->toContain($secret);
                }
            });
    } finally {
        ini_set('zend.exception_ignore_args', (string) $original);
    }

    expect(resolve(PrepareDocumentAction::class)->handle(P::invoice())->document->header->documentNumber)->toBe(2)
        ->and($store->current(SequenceFixtures::scope()))->toBe(2);
});

it('resumes a numbered document with the same unsigned xml and no reservation', function (): void {
    app()->when(BuildIudAction::class)->needs(Randomizer::class)->give(fn (): Randomizer => new Randomizer(new Mt19937(42)));
    $document = P::invoice(['serie' => 'A', 'documentNumber' => 5]);

    $first  = resolve(PrepareDocumentAction::class)->handle($document);
    $second = resolve(PrepareDocumentAction::class)->handle($document);

    expect($first->allocated)->toBeFalse()
        ->and($first->document->header->documentNumber)->toBe(5)
        ->and($second->unsignedXml)->toBe($first->unsignedXml)
        ->and($second->iud)->toBe($first->iud)
        ->and($this->store->current(SequenceFixtures::scope()))->toBe(0);
});

it('lets a failure of a resumed document through unchanged', function (): void {
    expect(fn (): PreparedDocument => resolve(PrepareDocumentAction::class)->handle(P::invoice(['documentNumber' => 5])))
        ->toFailValidationOn('header.serie', 'The header.serie is required to write the XML document.')
        ->and($this->store->current(SequenceFixtures::scope()))->toBe(0);
});
