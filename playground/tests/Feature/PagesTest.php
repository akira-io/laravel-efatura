<?php

declare(strict_types=1);

beforeEach(fn () => storeTestCertificate());

it('renders every playground page', function (string $route, string $heading): void {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('<h1>'.$heading.'</h1>', false);
})->with([
    'dashboard' => ['dashboard', 'Dashboard'],
    'document' => ['document', 'Document'],
    'sign' => ['sign', 'Sign'],
    'dfa' => ['dfa', 'DFA'],
    'currency' => ['currency', 'Currency'],
    'doctor' => ['doctor', 'Doctor'],
    'issue' => ['issue', 'Issue'],
]);

it('prefills the invoice form with the sample lines', function (): void {
    $this->get(route('document'))
        ->assertOk()
        ->assertSee('value="Consulting hours"', false)
        ->assertSee('name="lines[2][description]"', false);
});
