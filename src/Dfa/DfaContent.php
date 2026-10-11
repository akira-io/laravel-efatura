<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Carbon\CarbonImmutable;

final readonly class DfaContent
{
    /**
     * @param list<DfaLine>       $lines
     * @param list<DfaConversion> $conversions
     */
    public function __construct(
        public DfaParty $emitter,
        public ?DfaParty $receiver,
        public string $documentType,
        public string $series,
        public string $documentNumber,
        public CarbonImmutable $issuedAt,
        public array $lines,
        public ?DfaTotals $totals,
        public array $conversions,
    ) {}

    public function issuedAtLabel(): string
    {
        return $this->issuedAt->format('d/m/Y H:i:s');
    }
}
