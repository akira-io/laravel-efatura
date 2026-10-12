<?php

declare(strict_types=1);

beforeEach(fn () => storeTestCertificate());

it('writes the unsigned XML of the sample invoice and validates it against the XSD', function (): void {
    $this->post(route('document.preview'), sampleInvoice())
        ->assertOk()
        ->assertSee('&lt;Dfe xmlns=&quot;urn:cv:efatura:xsd:v1.0&quot;', false)
        ->assertSee('<span class="ok">valid</span>', false)
        ->assertSee('3450.00 CVE');
});

it('reports the fiscal path the package refuses when the amount to pay does not reconcile', function (): void {
    $this->from(route('document'))
        ->post(route('document.preview'), sampleInvoice(['payable_override' => '1']))
        ->assertRedirect(route('document'))
        ->assertSessionHasErrors('totals.payableAmount');
});

it('refuses a form without any line', function (): void {
    $this->from(route('document'))
        ->post(route('document.preview'), sampleInvoice(['lines' => [['description' => '', 'quantity' => '', 'unit_price' => '']]]))
        ->assertRedirect(route('document'))
        ->assertSessionHasErrors('lines');
});
