<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Storage;

it('signs the sample invoice with the test certificate and verifies the signature', function (): void {
    storeTestCertificate();

    $this->post(route('sign.run'), sampleInvoice())
        ->assertOk()
        ->assertSee('ds:Signature')
        ->assertSee('e-Fatura Playground Test Signer (not ICP-CV)')
        ->assertSeeInOrder(['Signature', '<span class="ok">valid</span>', 'XSD and signature check', '<span class="ok">valid</span>'], false);
});

it('names the package error code when the signing certificate cannot be read', function (): void {
    Storage::fake('certificates');

    $this->from(route('sign'))
        ->post(route('sign.run'), sampleInvoice())
        ->assertRedirect(route('sign'))
        ->assertSessionHasErrors(['efatura' => 'CertificateException: certificate.unreadable (efatura.certificates.certificate_path)']);
});
