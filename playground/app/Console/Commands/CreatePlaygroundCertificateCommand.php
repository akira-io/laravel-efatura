<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateTestCertificateAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;

#[Signature('playground:certificate {--force : Replace an existing test certificate}')]
#[Description('Create a self-signed test signing certificate for the playground (never ICP-CV, local use only)')]
final class CreatePlaygroundCertificateCommand extends Command
{
    public function handle(CreateTestCertificateAction $createCertificate, Factory $filesystems, Repository $config): int
    {
        $disk = $filesystems->disk($config->string('playground.certificate.disk'));
        $certificatePath = $config->string('playground.certificate.certificate_path');
        $privateKeyPath = $config->string('playground.certificate.private_key_path');

        if ($disk->exists($certificatePath) && ! $this->option('force')) {
            $this->components->info('The test certificate already exists. Pass --force to replace it.');

            return self::SUCCESS;
        }

        $certificate = $createCertificate->handle($config->integer('playground.certificate.valid_days'));
        $disk->put($certificatePath, $certificate->certificatePem);
        $disk->put($privateKeyPath, $certificate->privateKeyPem);

        $this->components->info('Test certificate written to '.$disk->path($certificatePath).'.');
        $this->components->warn('It is self-signed and not issued under ICP-CV: use it only against the fake transport or a test repository.');

        return self::SUCCESS;
    }
}
