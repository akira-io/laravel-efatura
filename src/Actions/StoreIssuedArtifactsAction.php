<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\ArtifactStore;
use Akira\Efatura\Dfa\RenderedDfa;
use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\StorageException;
use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Storage\ResponseEvidence;
use Akira\Efatura\Storage\StoredArtifact;

final readonly class StoreIssuedArtifactsAction
{
    public function __construct(private ArtifactStore $store, private ResponseEvidence $evidence) {}

    /**
     * @return list<StoredArtifact>
     */
    public function handle(IssuedDocument $issued, ?RenderedDfa $dfa = null): array
    {
        $iud = $issued->prepared->iud;

        if (! $issued->authorized()) {
            throw StorageException::notAuthorized($iud);
        }

        if ($dfa instanceof RenderedDfa && ($dfa->iud !== $iud || $dfa->model !== EmissionMode::Online)) {
            throw StorageException::dfaMismatch($iud, $dfa->iud, $dfa->model->name);
        }

        $artifacts = [
            $this->store->put($iud, ArtifactKind::SignedXml, $issued->prepared->signed->xml),
            $this->store->put($iud, ArtifactKind::Archive, $issued->prepared->archive->bytes),
            $this->store->put($iud, ArtifactKind::Response, $this->evidence->encode($issued->result, $issued->submission)),
        ];

        return $dfa instanceof RenderedDfa ? [...$artifacts, $this->store->put($iud, ArtifactKind::Dfa, $dfa->bytes)] : $artifacts;
    }
}
