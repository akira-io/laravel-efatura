<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\DocumentXmlSerializers;
use Akira\Efatura\Xml\Serializers\TransmissionXmlSerializer;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use Illuminate\Validation\ValidationException;

final readonly class BuildDocumentXmlAction
{
    public function __construct(
        private DocumentXmlSerializers $serializers,
        private ParseIudAction $parseIud,
        private TransmissionXmlSerializer $transmission,
    ) {}

    public function handle(DocumentData $document, string $iud, Environment $repository, bool $isSpecimen = false): string
    {
        $document = $document::from($document);
        $xml      = new XmlWriter;

        $this->verifyIdentifier($xml, $document, $iud, $repository);

        $dfe = $xml->document('Dfe');
        $xml->attribute($dfe, 'Version', Fiscal::XML_SCHEMA_VERSION, 'version');
        $xml->attribute($dfe, 'Id', $iud, 'iud');
        $xml->attribute($dfe, 'DocumentTypeCode', XmlValue::integer($document->type()->code()), 'documentTypeCode');
        $xml->element($dfe, 'IsSpecimen', $isSpecimen ? XmlValue::boolean(true) : null, 'isSpecimen');

        $this->serializers->for($document->type())->append($xml, $dfe, $document);
        $this->transmission->append($xml, $dfe, $document->emission, 'emission');
        $xml->requiredElement($dfe, 'RepositoryCode', XmlValue::integer($repository->value), 'repositoryCode');

        return $xml->toXml();
    }

    private function verifyIdentifier(XmlWriter $xml, DocumentData $document, string $iud, Environment $repository): void
    {
        $number     = $xml->required($document->header->documentNumber, 'header.documentNumber');
        $identifier = $this->parseIud->handle($iud);

        $matches = $identifier->repository === $repository
            && Fiscal::format($identifier->issueDate, Fiscal::DATE_FORMAT, instant: true)
                === Fiscal::format($document->header->issueDate, Fiscal::DATE_FORMAT, instant: true)
            && $identifier->emitterTaxId === $document->emitter->taxId?->value
            && $identifier->ledCode === $document->header->ledCode
            && $identifier->documentType === $document->type()
            && $identifier->documentNumber === $number;

        if (! $matches) {
            throw ValidationException::withMessages(['iud' => __('efatura::efatura.validation.iud_mismatch', ['attribute' => 'iud'])]);
        }
    }
}
