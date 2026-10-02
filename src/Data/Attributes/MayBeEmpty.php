<?php

declare(strict_types=1);

namespace Akira\Efatura\Data\Attributes;

use Attribute;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Support\Validation\RequiringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class MayBeEmpty extends Present implements RequiringRule {}
