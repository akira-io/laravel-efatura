<?php

declare(strict_types=1);

namespace App\Actions;

use Akira\Efatura\Actions\BuildDocumentXmlAction;
use Akira\Efatura\Actions\NumberDocumentAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Xml\SchemaViolation;
use App\Data\DocumentPreview;

final readonly class PreviewDocumentXmlAction
{
    public function __construct(
        private EfaturaConfig $config,
        private NumberDocumentAction $numberDocument,
        private BuildDocumentXmlAction $buildXml,
        private SchemaValidator $schemas,
    ) {}

    public function handle(DocumentData $document): DocumentPreview
    {
        $repository = $this->config->environment->environment;
        $numbered = $this->numberDocument->handle($document, $repository);
        $xml = $this->buildXml->handle($numbered->document, $numbered->iud, $repository);

        try {
            $this->schemas->validate($xml);
            $violations = [];
        } catch (SchemaValidationException $exception) {
            $violations = collect($exception->violations)
                ->map(fn (SchemaViolation $violation): string => 'line '.$violation->line.': '.$violation->message)
                ->all();
        }

        return new DocumentPreview($numbered->document, $numbered->iud, $xml, $violations);
    }
}
