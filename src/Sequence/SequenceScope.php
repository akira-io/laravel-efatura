<?php

declare(strict_types=1);

namespace Akira\Efatura\Sequence;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\SequenceException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;

final readonly class SequenceScope
{
    public function __construct(
        public string $emitterTaxId,
        public int $year,
        public int $ledCode,
        public DocumentType $documentType,
    ) {
        if (! FiscalRules::isCvTaxId($emitterTaxId) || ! self::isFiscalYear($year) || ! FiscalRules::isLedCode($ledCode)) {
            throw DefinitionException::sequenceScope();
        }
    }

    public static function forDocument(DocumentData $document): self
    {
        return new self(
            (string) $document->emitter->taxId?->value,
            Fiscal::local($document->header->issueDate)->year,
            $document->header->ledCode,
            $document->type(),
        );
    }

    public function key(): string
    {
        return implode(':', [$this->emitterTaxId, $this->year, $this->ledCode, $this->documentType->code()]);
    }

    public function allocated(int $number): int
    {
        if ($number > Fiscal::MAX_DOCUMENT_NUMBER) {
            throw SequenceException::exhausted($this);
        }

        return $number;
    }

    private static function isFiscalYear(int $year): bool
    {
        return Fiscal::parse((string) $year, 'Y') instanceof CarbonImmutable && $year < CarbonImmutable::parse(Fiscal::IDENTIFIER_DATE_LIMIT)->year;
    }
}
