<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ParseEventIdAction;
use Akira\Efatura\Actions\PrepareEventAction;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\CertificateException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Packaging\PreparedEvent;
use Akira\Efatura\Tests\Support\EventFixtures;
use Akira\Efatura\Tests\Support\PackageFixtures;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\SignatureVerifier as V;
use Akira\Efatura\Xml\LibxmlSchemaValidator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

beforeEach(function (): void {
    P::configure();
    $this->directory = sys_get_temp_dir() . '/efatura-preparation-' . Str::uuid()->toString();
    new Filesystem()->ensureDirectoryExists($this->directory);
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->directory);
});

it('identifies, writes, validates, signs and packages an event with the transmitter tax id', function (Closure $event, SignatureProfile $profile): void {
    $prepared = resolve(PrepareEventAction::class)->handle($event(), $profile);
    $parsed   = resolve(ParseEventIdAction::class)->handle($prepared->eventId);
    $entries  = PackageFixtures::entries($prepared->archive->bytes, $this->directory);

    V::verify($prepared->signed->xml);

    expect($prepared->eventId)->toBe('CV3261002120000' . P::TRANSMITTER)
        ->and($parsed->taxId)->toBe(P::TRANSMITTER)
        ->and($parsed->repository)->toBe(Environment::Test)
        ->and($prepared->event->emission?->transmitterTaxId?->value)->toBe(P::TRANSMITTER)
        ->and(fn () => resolve(SchemaValidator::class)->validate($prepared->unsignedXml))->not->toThrow(Throwable::class)
        ->and($prepared->signed->id)->toBe($prepared->eventId)
        ->and($prepared->signed->profile)->toBe($profile)
        ->and($prepared->archive->kind)->toBe(PackageKind::Events)
        ->and($prepared->archive->entries)->toBe([$prepared->eventId . '.xml'])
        ->and(array_column($entries, 'contents'))->toBe([$prepared->signed->xml]);
})->with([
    'cancellation'   => [fn (): EventData => P::event([])],
    'unused numbers' => [P::unusedNumbers(...)],
])->with(SignatureProfile::cases());

it('identifies an event by the transmitter of its explicit emission context', function (): void {
    $relay = [
        'transmitterTaxId' => ['value' => '900800700', 'countryCode' => 'CV'],
        'software'         => ['code' => 'RELAY', 'name' => 'Relay App', 'version' => '2'],
    ];
    $prepared = resolve(PrepareEventAction::class)->handle(P::event(['emission' => $relay]));

    expect($prepared->eventId)->toEndWith('900800700')
        ->and($prepared->unsignedXml)->toContain('RELAY')->not->toContain('Fiscal App');
});

it('refuses an explicit emission context without a transmitter', function (): void {
    expect(fn (): PreparedEvent => resolve(PrepareEventAction::class)->handle(P::event(['emission' => ['issueMode' => 1]])))
        ->toFailValidationOn('emission.transmitterTaxId', 'The emission.transmitterTaxId is required to write the XML document.');
});

it('refuses credentials that cannot sign', function (): void {
    P::configure(certificate: P::expired());

    expect(fn (): PreparedEvent => resolve(PrepareEventAction::class)->handle(EventData::from(EventFixtures::transmitted(['iuds' => [EventFixtures::iud()]]))))
        ->toThrow(CertificateException::class, 'certificate.expired');
});

it('lets the schema refuse an event before it is signed', function (): void {
    app()->instance(SchemaValidator::class, new readonly class (resolve(LibxmlSchemaValidator::class)) implements SchemaValidator
    {
        public function __construct(private LibxmlSchemaValidator $validator) {}

        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            $this->validator->validate($xml, SignatureProfile::InternallyDetached);
        }
    });

    expect(fn (): PreparedEvent => resolve(PrepareEventAction::class)->handle(P::event()))
        ->toThrow(SchemaValidationException::class, 'xml.schema_invalid');
});
