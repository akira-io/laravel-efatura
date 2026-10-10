<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Contracts\XmlSigner;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\PreparationException;
use Akira\Efatura\Packaging\PreparedDocument;
use Illuminate\Validation\ValidationException;

final readonly class PrepareDocumentAction
{
    public function __construct(
        private EfaturaConfig $config,
        private ResolveEmissionContextAction $resolveEmission,
        private CertificateLoader $certificates,
        private NumberDocumentAction $numberDocument,
        private BuildDocumentXmlAction $buildXml,
        private SchemaValidator $schemas,
        private XmlSigner $signer,
        private Packager $packager,
    ) {}

    public function handle(DocumentData $document, bool $isSpecimen = false, SignatureProfile $profile = SignatureProfile::Enveloped): PreparedDocument
    {
        $document    = $this->resolveEmission->handle($document::from($document));
        $credentials = $this->certificates->load($this->config->certificates);
        $repository  = $this->config->environment->environment;
        $numbered    = $this->numberDocument->handle($document, $repository);

        try {
            $unsignedXml = $this->buildXml->handle($numbered->document, $numbered->iud, $repository, $isSpecimen);
            $this->schemas->validate($unsignedXml);
            $signed  = $this->signer->sign($unsignedXml, $credentials, $profile);
            $archive = $this->packager->package([$signed->xml]);
        } catch (EfaturaException|ValidationException $exception) {
            throw $numbered->allocated ? PreparationException::failedAfterAllocation($numbered, $exception) : $exception;
        }

        return new PreparedDocument($numbered->document, $numbered->iud, $unsignedXml, $signed, $archive, $numbered->allocated);
    }
}
