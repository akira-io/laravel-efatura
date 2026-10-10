<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Tests\Support\DocumentXmlGraphs as X;
use Akira\Efatura\Tests\Support\SchemaFixtures as S;

afterEach(function (): void {
    libxml_set_external_entity_loader(null);
    libxml_clear_errors();
    libxml_use_internal_errors(false);
});

it('keeps an error the caller left pending', function (): void {
    libxml_use_internal_errors(true);
    new DOMDocument()->loadXML('<pending>');
    $pending = libxml_get_errors();

    $exception = S::rejection(S::invalidInvoice());

    expect(array_slice(libxml_get_errors(), 0, count($pending)))->toEqual($pending)
        ->and($exception->violations)->toHaveCount(1)
        ->and($exception->violations[0]->message)->toContain('Serie');
});

it('restores the internal error setting and leaves no error behind', function (bool $internalErrors): void {
    libxml_use_internal_errors($internalErrors);

    S::rejection(S::invalidInvoice());

    expect(libxml_use_internal_errors())->toBe($internalErrors)
        ->and(libxml_get_errors())->toBe([])
        ->and(libxml_get_last_error())->toBeFalse();
})->with([true, false]);

it('restores the previous entity loader even when validation fails', function (): void {
    $loader = static fn (?string $public, string $system): ?string => null;
    libxml_set_external_entity_loader($loader);

    S::rejection(S::invalidInvoice());

    expect(libxml_get_external_entity_loader())->toBe($loader);
});

it('never shares errors between two validations', function (): void {
    $first = S::rejection(S::invalidInvoice());

    resolve(SchemaValidator::class)->validate(X::fixture(DocumentType::Invoice));
    $second = S::rejection(S::invalidInvoice());

    expect($second->violations)->toEqual($first->violations)
        ->and($second->violations)->toHaveCount(1);
});
