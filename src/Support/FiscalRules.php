<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

final class FiscalRules
{
    private const string SERIES = '[A-Za-z0-9]+(?:[_-][A-Za-z0-9]+)*';

    private const string IUD = 'CV[0-9][0-9]{2}(?:0[1-9]|1[012])(?:0[1-9]|[12][0-9]|3[01])[1-9][0-9]{35}';

    private const string URL_TOKEN = '[A-Za-z0-9_-]';

    /**
     * @return list<string>
     */
    public static function text(int $minimum, int $maximum): array
    {
        return ['string', 'min:' . $minimum, 'max:' . $maximum, 'regex:/\A[^\s]+(?: [^\s]+)*\z/u'];
    }

    /**
     * @return list<string>
     */
    public static function code(int $maximum = 50): array
    {
        return ['string', 'min:1', 'max:' . $maximum, 'regex:/\A[^\s]+\z/u'];
    }

    /**
     * @return list<string>
     */
    public static function series(): array
    {
        return ['string', 'max:20', 'regex:/\A' . self::SERIES . '\z/'];
    }

    /**
     * @return list<string>
     */
    public static function iud(): array
    {
        return ['string', 'regex:/\A' . self::IUD . '\z/'];
    }

    /**
     * @return list<string>
     */
    public static function fiscalDocumentReference(): array
    {
        return ['string', 'regex:~\A(?:' . self::IUD . '|[1-9]/[0-9]{4}/' . self::SERIES . '/[0-9]{1,9})\z~'];
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
     * @return list<string>
     */
    public static function phone(): array
    {
        return ['string', 'regex:/\A[0-9]{7,20}\z/'];
    }

    /**
     * @return list<string>
     */
    public static function website(): array
    {
        $token = self::URL_TOKEN;

        return ['string', 'max:256', 'regex:~\A(?:https?://)?' . $token . '+(?:\.' . $token . '+)*(?::[0-9]+)?(?:/[-._A-Za-z0-9]+)*'
            . '(?:\?(?:' . $token . '+=[+%A-Za-z0-9_-]*)(?:&' . $token . '+=[+%A-Za-z0-9_-]*)*)?(?:\#[^\s]*)?\z~u'];
    }
}
