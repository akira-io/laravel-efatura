<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum OAuthScope: string
{
    case DfeCreate               = 'cv_ef_dfe_create';
    case DfeReadIud              = 'cv_ef_dfe_read_iud';
    case DfeSelfBillingAuthorize = 'cv_ef_dfe_self_billing_authorize';
    case EventCreate             = 'cv_ef_event_create';
    case OfflineAccess           = 'offline_access';
}
