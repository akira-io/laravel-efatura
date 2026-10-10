<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Enums\OAuthScope;
use Akira\Efatura\OAuth\AccessToken;

interface AccessTokenProvider
{
    /**
     * @param list<OAuthScope> $scopes
     */
    public function token(string $emitterTaxId, array $scopes): AccessToken;

    /**
     * @param list<OAuthScope> $scopes
     */
    public function forget(string $emitterTaxId, array $scopes): void;
}
