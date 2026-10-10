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
use Akira\Efatura\Exceptions\PreparationException;
use Akira\Efatura\Packaging\PreparedDocument;
use Throwable;

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
        $validated   = $document::from($document);
        $document    = $validated::from([...$validated->toPayload(), 'emission' => $this->resolveEmission->handle($validated->emission)->toPayload()]);
        $credentials = $this->certificates->load($this->config->certificates);
        $repository  = $this->config->environment->environment;
        $numbered    = $this->numberDocument->handle($document, $repository);

        try {
            $unsignedXml = $this->buildXml->handle($numbered->document, $numbered->iud, $repository, $isSpecimen);
            $this->schemas->validate($unsignedXml);
            $signed  = $this->signer->sign($unsignedXml, $credentials, $profile);
            $archive = $this->packager->package([$signed->xml]);
        } catch (Throwable $throwable) {
            throw $numbered->allocated ? PreparationException::failedAfterAllocation($numbered, $throwable) : $throwable;
        }

        return new PreparedDocument($numbered->document, $numbered->iud, $unsignedXml, $signed, $archive, $numbered->allocated);
    }
}
