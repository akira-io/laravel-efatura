<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum SignatureProfile: string
{
    case Enveloped          = 'EnvelopedSignature';
    case InternallyDetached = 'InternallyDetachedSignature';
}
