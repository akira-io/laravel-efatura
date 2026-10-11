<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Dfa\DfaContentReader;
use Akira\Efatura\Dfa\DfaData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\DfaException;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Packaging\PreparedDocument;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class BuildDfaDataAction
{
    private const string IUC = '/\A[0-9]{4}\/[0-9]+\z/';

    public function __construct(
        private EfaturaConfig $config,
        private ParseIudAction $parseIud,
        private DfaContentReader $reader,
    ) {}

    public function handle(IssuedDocument|PreparedDocument $source, ?string $iuc = null): DfaData
    {
        $prepared = $source instanceof IssuedDocument ? $source->prepared : $source;
        $mode     = $this->mode($source);

        try {
            $documentNumber = $this->parseIud->handle($prepared->iud)->documentNumber;
        } catch (ValidationException) {
            throw DfaException::invalidIud();
        }

        return new DfaData(
            $mode,
            $prepared->iud,
            $mode === EmissionMode::Off ? null : $this->config->dfa->baseUrl . '/' . $prepared->iud,
            $source instanceof IssuedDocument ? $source->result->authorizationCode : null,
            $mode === EmissionMode::Off ? $this->iuc($prepared, $iuc) : null,
            $this->reader->read($prepared->document, $documentNumber, $mode !== EmissionMode::Off),
        );
    }

    private function mode(IssuedDocument|PreparedDocument $source): EmissionMode
    {
        if ($source instanceof IssuedDocument) {
            return $source->authorized() ? EmissionMode::Online : throw DfaException::notAuthorized($source->prepared->iud);
        }

        $mode = $source->document->emission->issueMode ?? EmissionMode::Online;

        return $mode === EmissionMode::Online ? throw DfaException::notAuthorized($source->iud) : $mode;
    }

    private function iuc(PreparedDocument $prepared, ?string $given): string
    {
        $contingency = $prepared->document->emission?->contingency;
        $recorded    = $contingency instanceof ContingencyData ? $contingency->iuc : null;

        if ($recorded !== null && $given !== null && $recorded !== $given) {
            throw DfaException::iucMismatch($prepared->iud);
        }

        $iuc = $recorded ?? $given ?? throw DfaException::iucRequired($prepared->iud);

        return Str::isMatch(self::IUC, $iuc) ? $iuc : throw DfaException::invalidIuc();
    }
}
