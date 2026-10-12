<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Testing\FakeTransport;

it('issues the sample invoice through the fake transport and shows its authorization code', function (): void {
    storeTestCertificate();

    $this->post(route('issue.run'), sampleInvoice())
        ->assertOk()
        ->assertSee('<span class="ok">authorized</span>', false)
        ->assertSee('FAKE-CV3');

    $transport = resolve(Transport::class);

    expect($transport)->toBeInstanceOf(FakeTransport::class)
        ->and($transport->submitted())->toHaveCount(1);
});
