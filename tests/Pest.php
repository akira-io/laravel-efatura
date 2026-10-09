<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

uses(TestCase::class)->in(__DIR__);

expect()->extend('toFailValidationOn', function (string $field, string $message): Expectation {
    try {
        ($this->value)();
    } catch (ValidationException $exception) {
        $errors = $exception->errors();

        expect(array_keys($errors))->toContain($field)
            ->and($errors[$field])->toContain($message);

        return $this;
    } catch (EfaturaValidationException $exception) {
        expect($exception->field())->toBe($field)
            ->and($exception->getMessage())->toBe($message);

        return $this;
    }

    throw new ExpectationFailedException(sprintf('Validation passed, expected a failure on [%s].', $field));
});
