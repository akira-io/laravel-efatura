<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Transport\SubmissionResult;

final readonly class SubmitArchiveAction
{
    public function __construct(private Transport $transport, private EfaturaConfig $config) {}

    public function handle(PackagedArchive $archive): SubmissionResult
    {
        throw_unless(
            $archive->repository === $this->config->environment->environment,
            TransportException::because(TransportFailure::RepositoryMismatch, $archive, field: 'efatura.environment'),
        );

        return $this->transport->submit($archive);
    }
}
