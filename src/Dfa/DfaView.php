<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Contracts\QrCodeRenderer;
use Illuminate\Contracts\View\Factory;

final readonly class DfaView
{
    public const string VIEW = 'efatura::dfa.document';

    public function __construct(
        private Factory $views,
        private QrCodeRenderer $qrCodes,
    ) {}

    public function render(DfaData $dfa): string
    {
        return $this->views->make(self::VIEW, ['dfa' => $dfa, 'qrCode' => $this->qrCode($dfa)])->render();
    }

    private function qrCode(DfaData $dfa): ?string
    {
        return $dfa->qrPayload === null ? null : 'data:image/svg+xml;base64,' . base64_encode($this->qrCodes->render($dfa->qrPayload));
    }
}
