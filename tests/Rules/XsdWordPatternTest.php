<?php

declare(strict_types=1);

use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\SelfBillingData;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Tests\Support\XsdTypeProbe;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

it('accepts an email exactly when the official schema does', function (string $email, bool $accepted): void {
    $rule = Validator::make(['email' => $email], ['email' => FiscalRules::email()])->passes();

    expect($rule)->toBe($accepted)
        ->and(new XsdTypeProbe('stEmail')->accepts($email))->toBe($accepted);
})->with([
    'plain'                     => ['billing@example.cv', true],
    'inner underscore'          => ['billing_team@example.cv', true],
    'separated domain labels'   => ['a-b.c_d@sub-domain.example.cv', true],
    'symbol in local part'      => ['a+b@example.cv', true],
    'accented letter'           => ['josé@exemplo.cv', true],
    'digits only'               => ['1@2.3', true],
    'longest'                   => [Str::repeat('a', 251) . '@x.cv', true],
    'leading underscore'        => ['_billing@example.cv', false],
    'trailing underscore'       => ['billing_@example.cv', false],
    'doubled underscore'        => ['billing__team@example.cv', false],
    'underscore after dot'      => ['billing._team@example.cv', false],
    'underscore opening domain' => ['billing@_example.cv', false],
    'dot before at'             => ['billing.@example.cv', false],
    'domain without dot'        => ['billing@example', false],
    'trailing dot'              => ['billing@example.cv.', false],
    'empty local part'          => ['@example.cv', false],
    'space'                     => ['bil ling@example.cv', false],
    'legacy punctuation U+166D' => ["a\u{166D}b@example.cv", false],
    'legacy format U+17B4'      => ["a\u{17B4}b@example.cv", false],
    'legacy bracket U+23B4'     => ["a\u{23B4}b@example.cv", false],
    'too long'                  => [Str::repeat('a', 252) . '@x.cv', false],
]);

it('lets the schema accept every character the email rule accepts', function (): void {
    $pattern  = Str::after((string) collect(FiscalRules::email())->last(), 'regex:');
    $accepted = [];
    foreach ([...range(0x20, 0xD7FF), ...range(0xE000, 0xFFFD), ...range(0x10000, 0x10FFFF)] as $codePoint) {
        $email = 'a' . mb_chr($codePoint, 'UTF-8') . 'b@example.cv';
        if (Str::isMatch($pattern, $email)) {
            $accepted[] = $email;
        }
    }

    expect($accepted)->toContain('aAb@example.cv', 'aéb@example.cv', 'a7b@example.cv', 'a+b@example.cv')
        ->not->toContain('a_b@example.cv', 'a b@example.cv')
        ->and(new XsdTypeProbe('stEmail')->accepts(...$accepted))->toBeTrue();
});

it('accepts a self-billing authorization id exactly when the official schema does', function (string $id, bool $accepted): void {
    expect(Validator::make(['id' => $id], ['id' => FiscalRules::uuid()])->passes())->toBe($accepted)
        ->and(new XsdTypeProbe('stUUID')->accepts($id))->toBe($accepted);
})->with([
    'uuid'                => ['12345678-1234-1234-1234-123456789abc', true],
    'symbols and letters' => ['ação+$^x-1234-1234-1234-123456789abc', true],
    'underscore'          => ['1234567_-1234-1234-1234-123456789abc', false],
    'legacy U+23B5'       => ["1234567\u{23B5}-1234-1234-1234-123456789abc", false],
    'short group'         => ['1234567-1234-1234-1234-123456789abcd', false],
]);

it('refuses an email and an authorization id the schema would refuse', function (): void {
    expect(fn (): ContactsData => ContactsData::from(['email' => '_billing@example.cv']))
        ->toFailValidationOn('email', 'The email field format is invalid.')
        ->and(fn (): SelfBillingData => SelfBillingData::from(['authorizationId' => '________-____-____-____-____________', 'authorizationCode' => '1234']))
        ->toFailValidationOn('authorizationId', 'The authorization id field format is invalid.');
});
