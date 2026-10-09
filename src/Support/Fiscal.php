<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class Fiscal
{
    public const string TIMEZONE = 'Atlantic/Cape_Verde';

    public const string COUNTRY = 'CV';

    public const string CURRENCY = 'CVE';

    public const int AMOUNT_SCALE = 5;

    public const int INTEGER_DIGITS = 15;

    public const string EARLIEST_DATE = '2021-01-01';

    public const string DATE_FORMAT = 'Y-m-d';

    public const string TIME_FORMAT = 'H:i:s';

    public const string DATE_TIME_FORMAT = 'Y-m-d\TH:i:s';

    public const string SALES_RECEIPT_IDENTIFIED_RECEIVER_AMOUNT = '20000';

    public const string XML_NAMESPACE = 'urn:cv:efatura:xsd:v1.0';

    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return $moment->toImmutable()->setTimezone(self::TIMEZONE);
    }

    public static function format(CarbonInterface $moment, string $format, bool $instant): string
    {
        return ($instant ? self::local($moment) : $moment)->format($format);
    }
}
