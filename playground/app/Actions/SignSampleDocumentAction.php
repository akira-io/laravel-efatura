<?php

declare(strict_types=1);

namespace App\Actions;

use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Actions\ValidateXmlDocumentAction;
use Akira\Efatura\Actions\VerifyXmlSignatureAction;
use Akira\Efatura\Data\DocumentData;
use App\Data\SignedDocument;

final readonly class SignSampleDocumentAction
{
    public function __construct(
        private PrepareDocumentAction $prepareDocument,
        private VerifyXmlSignatureAction $verifySignature,
        private ValidateXmlDocumentAction $validateXml,
    ) {}

    public function handle(DocumentData $document): SignedDocument
    {
        $prepared = $this->prepareDocument->handle($document);

        return new SignedDocument(
            $prepared,
            $this->verifySignature->handle($prepared->signed->xml),
            $this->validateXml->handle($prepared->signed->xml),
        );
    }
}
