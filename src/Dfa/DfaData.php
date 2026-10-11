<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Enums\EmissionMode;

final readonly class DfaData
{
    public function __construct(
        public EmissionMode $mode,
        public string $iud,
        public ?string $qrPayload,
        public ?string $authorizationCode,
        public ?string $iuc,
        public DfaContent $content,
    ) {}

    public function pendingAuthorization(): bool
    {
        return $this->mode !== EmissionMode::Online;
    }

    public function showsItemCodes(): bool
    {
        return $this->mode !== EmissionMode::Off;
    }

    public function contingencyMode(): string
    {
        return $this->fillsCustomerByHand() ? 'OFF' : 'OFFLINE';
    }

    public function fillsCustomerByHand(): bool
    {
        return $this->mode === EmissionMode::Off;
    }

    public function filename(): string
    {
        return $this->iud . '.pdf';
    }
}
