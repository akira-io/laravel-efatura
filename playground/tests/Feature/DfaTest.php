<?php

declare(strict_types=1);

beforeEach(fn () => storeTestCertificate());

it('renders the DFA model as an inline PDF', function (string $model): void {
    $response = $this->post(route('dfa.render'), sampleInvoice(['model' => $model]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline; filename="CV3');
})->with(['online', 'offline', 'off']);

it('offers the DFA as a download on request', function (): void {
    $response = $this->post(route('dfa.render'), sampleInvoice(['download' => '1']));

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename="CV3');
});
