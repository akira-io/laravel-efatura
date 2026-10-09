<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum DecimalViolation: string
{
    case InvalidDecimal        = 'invalid_decimal';
    case IntegerDigitsExceeded = 'integer_digits_exceeded';
    case ScaleExceeded         = 'decimal_scale_exceeded';
}
