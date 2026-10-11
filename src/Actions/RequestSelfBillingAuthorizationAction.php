<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Data\SelfBillingAuthorizationRequestData;
use Akira\Efatura\Transport\SelfBillingAuthorization;

final readonly class RequestSelfBillingAuthorizationAction
{
    public function __construct(private Transport $transport) {}

    public function handle(SelfBillingAuthorizationRequestData $request): SelfBillingAuthorization
    {
        return $this->transport->authorizeSelfBilling(SelfBillingAuthorizationRequestData::from($request));
    }
}
