<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Rules\IudCheckDigit;
use Akira\Efatura\Rules\NotBlank;
use Illuminate\Support\Str;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FiscalRules
{
    public const string CV_TAX_ID = '[1-9][0-9]{8}';

    public const string LED = '[1-9][0-9]{0,4}';

    private const string SERIES = '[A-Za-z0-9]+(?:[_-][A-Za-z0-9]+)*';

    private const string IUD = 'CV[0-9][0-9]{2}(?:0[1-9]|1[012])(?:0[1-9]|[12][0-9]|3[01])[1-9][0-9]{35}';

    private const string EVENT_ID = 'CV[0-9][0-9]{2}(?:0[1-9]|1[012])(?:0[1-9]|[12][0-9]|3[01])[0-9]{6}[1-9][0-9]{8}';

    private const string CLOCK_TIME = '(?:[01][0-9]|2[0-3])[0-5][0-9][0-5][0-9]';

    private const string URL_TOKEN = '[A-Za-z0-9_-]';

    private const string XML_NAME_START = 'A-Z_a-z\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}-\x{200D}'
        . '\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';

    private const string XML_NAME = '[' . self::XML_NAME_START . '][' . self::XML_NAME_START . '.0-9\x{B7}\x{300}-\x{36F}\x{203F}-\x{2040}-]*';

    private const string NAMESPACE_URI = '[A-Za-z][A-Za-z0-9+.-]*:[^\s"<>{}|\\\^`]+';

    private const array W3C_RESERVED_URIS = ['http://www.w3.org/2000/xmlns/', 'http://www.w3.org/XML/1998/namespace'];

    /**
     * @return list<string|NotBlank>
     */
    public static function text(int $minimum, int $maximum): array
    {
        return ['string', new NotBlank, 'min:' . $minimum, 'max:' . $maximum, 'regex:/\A[^\s]+(?: [^\s]+)*\z/u'];
    }

    /**
     * @return list<string|NotBlank>
     */
    public static function code(int $maximum = 50): array
    {
        return ['string', new NotBlank, 'min:1', 'max:' . $maximum, 'regex:/\A[^\s]+\z/u'];
    }

    /**
     * @return list<string|NotBlank>
     */
    public static function series(): array
    {
        return ['string', new NotBlank, 'max:20', 'regex:/\A' . self::SERIES . '\z/'];
    }

    public static function isIud(string $value): bool
    {
        return Str::isMatch('/\A' . self::IUD . '\z/', $value);
    }

    public static function isEventId(string $value): bool
    {
        return Str::isMatch('/\A' . self::EVENT_ID . '\z/', $value) && Str::isMatch('/\A' . self::CLOCK_TIME . '\z/', substr($value, 9, 6));
    }

    public static function isXmlName(string $value): bool
    {
        return Str::isMatch('/\A' . self::XML_NAME . '\z/u', $value);
    }

    public static function isXmlNamespace(string $value): bool
    {
        return Str::isMatch('/\A' . self::NAMESPACE_URI . '\z/', $value) && ! \in_array($value, self::W3C_RESERVED_URIS, true);
    }

    /**
     * @return list<string>
     */
    public static function xmlName(): array
    {
        return ['regex:/\A' . self::XML_NAME . '\z/u'];
    }

    /**
     * @return list<string|NotBlank>
     */
    public static function xmlNamespace(): array
    {
        $reserved = implode(',', [Fiscal::XML_NAMESPACE, ...self::W3C_RESERVED_URIS]);

        return [new NotBlank, 'max:256', 'regex:/\A' . self::NAMESPACE_URI . '\z/', 'not_in:' . $reserved];
    }

    /**
     * @return list<string|NotBlank|IudCheckDigit>
     */
    public static function iud(): array
    {
        return ['string', new NotBlank, 'regex:/\A' . self::IUD . '\z/', new IudCheckDigit];
    }

    /**
     * @return list<string|NotBlank|IudCheckDigit>
     */
    public static function fiscalDocumentReference(): array
    {
        return ['string', new NotBlank, 'regex:~\A(?:' . self::IUD . '|[1-9]/[0-9]{4}/' . self::SERIES . '/[0-9]{1,9})\z~', new IudCheckDigit];
    }

    /**
     * @return list<string>
     */
    public static function documentNumber(): array
    {
        return ['integer', 'between:1,999999999'];
    }

    /**
     * @return list<string>
     */
    public static function ledCode(): array
    {
        return ['integer', 'between:1,99999'];
    }

    /**
     * @return list<string|NotBlank>
     */
    public static function phone(): array
    {
        return ['string', new NotBlank, 'regex:/\A[0-9]{7,20}\z/'];
    }

    /**
     * @return list<string|NotBlank>
     */
    public static function website(): array
    {
        $token = self::URL_TOKEN;

        return ['string', new NotBlank, 'max:256', 'regex:~\A(?:https?://)?' . $token . '+(?:\.' . $token . '+)*(?::[0-9]+)?(?:/[-._A-Za-z0-9]+)*'
            . '(?:\?(?:' . $token . '+=[+%A-Za-z0-9_-]*)(?:&' . $token . '+=[+%A-Za-z0-9_-]*)*)?(?:\#[^\s]*)?\z~u'];
    }

    /**
     * @param  array<string, list<mixed>> $choices
     * @return array<string, list<mixed>>
     */
    public static function exactlyOneOf(ValidationContext $context, array $choices): array
    {
        $fields = array_keys($choices);

        return collect($choices)->map(static function (array $rules, string $field) use ($context, $fields): array {
            $others = FieldPath::list($context, ...array_values(array_diff($fields, [$field])));

            return ['required_without_all:' . $others, 'prohibits:' . $others, ...$rules];
        })->all();
    }
}
