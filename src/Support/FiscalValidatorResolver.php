<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Spatie\LaravelData\Resolvers\DataValidationMessagesAndAttributesResolver;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

final class FiscalValidatorResolver extends DataValidatorResolver
{
    public function __construct(FiscalValidationRulesResolver $rules, DataValidationMessagesAndAttributesResolver $messages)
    {
        parent::__construct($rules, $messages);
    }
}
