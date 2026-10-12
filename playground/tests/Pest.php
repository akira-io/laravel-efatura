<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Sequence\InMemorySequenceStore;
use App\Actions\CreateTestCertificateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app()->instance(SequenceStore::class, new InMemorySequenceStore))
    ->in('Feature');

function storeTestCertificate(): void
{
    $certificate = resolve(CreateTestCertificateAction::class)->handle(365);
    $disk = Storage::fake('certificates');
    $disk->put('playground.crt', $certificate->certificatePem);
    $disk->put('playground.key', $certificate->privateKeyPem);
}

/**
 * @return array<string, mixed>
 */
function sampleInvoice(array $overrides = []): array
{
    return [
        'lines' => [['description' => 'Consulting', 'quantity' => '2', 'unit_price' => '1500']],
        'tax_percentage' => '15',
        ...$overrides,
    ];
}
