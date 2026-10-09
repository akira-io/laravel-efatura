<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\PartyData;
use Override;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CommonRulesDocumentData extends DocumentData
{
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly ?PartyData $receiver = null,
        public readonly ?EmissionContextData $emission = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(ValidationContext $context): array
    {
        return [];
    }
}
