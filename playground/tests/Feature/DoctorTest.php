<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Storage;

it('reports the local installation as ready once the test certificate exists', function (): void {
    storeTestCertificate();

    $this->get(route('doctor'))
        ->assertOk()
        ->assertSee('Ready to issue')
        ->assertSee('readiness.certificate_valid')
        ->assertSee('Local checks only');
});

it('reports a certificate it cannot read', function (): void {
    Storage::fake('certificates');

    $this->get(route('doctor'))
        ->assertOk()
        ->assertSee('Not ready')
        ->assertSee('certificate.unreadable');
});
