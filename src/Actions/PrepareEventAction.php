<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Contracts\XmlSigner;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventIdData;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Packaging\PreparedEvent;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Validation\ValidationException;

final readonly class PrepareEventAction
{
    public function __construct(
        private EfaturaConfig $config,
        private ResolveEmissionContextAction $resolveEmission,
        private CertificateLoader $certificates,
        private BuildEventIdAction $buildEventId,
        private BuildEventXmlAction $buildXml,
        private SchemaValidator $schemas,
        private XmlSigner $signer,
        private Packager $packager,
    ) {}

    public function handle(EventData $event, SignatureProfile $profile = SignatureProfile::Enveloped): PreparedEvent
    {
        $event       = $this->resolveEmission->handle(EventData::from($event));
        $transmitter = $event->emission?->transmitterTaxId->value ?? throw ValidationException::withMessages([
            'emission.transmitterTaxId' => __('efatura::efatura.validation.xml_required', ['attribute' => 'emission.transmitterTaxId']),
        ]);
        $credentials = $this->certificates->load($this->config->certificates);
        $repository  = $this->config->environment->environment;
        $eventId     = $this->buildEventId->handle(EventIdData::from([
            'repositoryCode' => $repository,
            'issueDateTime'  => Fiscal::format($event->issueDateTime, Fiscal::DATE_TIME_FORMAT, instant: true),
            'taxId'          => $transmitter,
        ]));

        $unsignedXml = $this->buildXml->handle($event, $eventId, $repository);
        $this->schemas->validate($unsignedXml);
        $signed = $this->signer->sign($unsignedXml, $credentials, $profile);

        return new PreparedEvent($event, $eventId, $unsignedXml, $signed, $this->packager->package([$signed->xml]));
    }
}
