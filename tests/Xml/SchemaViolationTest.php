<?php

declare(strict_types=1);

use Akira\Efatura\Xml\SchemaViolation;

it('keeps a libxml message with invalid utf-8 readable', function (): void {
    $error          = new LibXMLError;
    $error->line    = 3;
    $error->column  = 7;
    $error->level   = LIBXML_ERR_ERROR;
    $error->message = "Element '{urn:cv:efatura:xsd:v1.0}Note\xFF':   This element is not expected.\n";

    $violation = SchemaViolation::fromLibxml($error);

    expect(mb_check_encoding($violation->message, 'UTF-8'))->toBeTrue()
        ->and($violation->message)->toBe("Element \x27{urn:cv:efatura:xsd:v1.0}Note?\x27: This element is not expected.")
        ->and([$violation->line, $violation->column, $violation->level])->toBe([3, 7, LIBXML_ERR_ERROR]);
});
