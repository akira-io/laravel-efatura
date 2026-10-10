<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Str;

final class Fiscal
{
    public const string TIMEZONE = 'Atlantic/Cape_Verde';

    public const string COUNTRY = 'CV';

    public const string CURRENCY = 'CVE';

    public const int AMOUNT_SCALE = 5;

    public const int PERCENTAGE_SCALE = 3;

    public const int INTEGER_DIGITS = 15;

    public const int MAX_LINES = 1000;

    public const int MAX_REFERENCES = 1000;

    public const int MAX_EVENT_IUDS = 1000;

    public const int MAX_LIST_ENTRIES = 100;

    public const int MAX_DOCUMENT_NUMBER = 999_999_999;

    public const int MIN_RSA_KEY_BITS = 2048;

    public const string EARLIEST_DATE = '2021-01-01';

    public const string IDENTIFIER_DATE_LIMIT = '2100-01-01';

    public const string DATE_FORMAT = 'Y-m-d';

    public const string TIME_FORMAT = 'H:i:s';

    public const string DATE_TIME_FORMAT = 'Y-m-d\TH:i:s';

    public const string SALES_RECEIPT_IDENTIFIED_RECEIVER_AMOUNT = '20000';

    public const string XML_NAMESPACE = 'urn:cv:efatura:xsd:v1.0';

    public const string XML_SCHEMA_VERSION = '1.0';

    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return $moment->toImmutable()->setTimezone(self::TIMEZONE);
    }

    public static function format(CarbonInterface $moment, string $format, bool $instant): string
    {
        return ($instant ? self::local($moment) : $moment)->format($format);
    }

    public static function parse(string $value, string $format): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!' . $format, $value, self::TIMEZONE);
        } catch (InvalidFormatException) {
            return null;
        }

        if (! $date instanceof CarbonImmutable || $date->format($format) !== $value || self::precedesEarliestDate($date, $format)) {
            return null;
        }

        return $date;
    }

    private static function precedesEarliestDate(CarbonImmutable $date, string $format): bool
    {
        return Str::contains($format, ['Y', 'y']) && $date->format(self::DATE_FORMAT) < self::EARLIEST_DATE;
    }
}
