<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Transport\DocumentLookup;
use Akira\Efatura\Transport\SubmissionResult;

interface Transport
{
    public function submit(PackagedArchive $archive): SubmissionResult;

    public function lookupDocument(string $iud): DocumentLookup;
}
