<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Rules\NotBlank;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FiscalRules
{
    public const string CV_TAX_ID = '[1-9][0-9]{8}';

    public const string LED = '[1-9][0-9]{0,4}';

    private const string SERIES = '[A-Za-z0-9]+(?:[_-][A-Za-z0-9]+)*';

    private const string IUD = 'CV[0-9][0-9]{2}(?:0[1-9]|1[012])(?:0[1-9]|[12][0-9]|3[01])[1-9][0-9]{35}';

    private const string URL_TOKEN = '[A-Za-z0-9_-]';

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

    /**
     * @return list<string|NotBlank>
     */
    public static function iud(): array
    {
        return ['string', new NotBlank, 'regex:/\A' . self::IUD . '\z/'];
    }

    /**
     * @return list<string|NotBlank>
     */
    public static function fiscalDocumentReference(): array
    {
        return ['string', new NotBlank, 'regex:~\A(?:' . self::IUD . '|[1-9]/[0-9]{4}/' . self::SERIES . '/[0-9]{1,9})\z~'];
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
