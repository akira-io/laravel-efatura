<?php

declare(strict_types=1);

it('converts the EUR sample to CVE at the fixed rate and keeps the amount to pay in EUR', function (): void {
    $this->post(route('currency.convert'), sampleInvoice())
        ->assertOk()
        ->assertSee('380414.25 CVE')
        ->assertSee('3450.00 EUR at 1 EUR = 110.26500 CVE (fixed)');
});
