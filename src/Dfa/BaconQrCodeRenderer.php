<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Contracts\QrCodeRenderer;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final readonly class BaconQrCodeRenderer implements QrCodeRenderer
{
    private const int SIZE = 240;

    public function __construct(
        private LibraryRequirement $library = new LibraryRequirement('bacon/bacon-qr-code', Writer::class),
    ) {}

    public function render(string $payload): string
    {
        $this->library->ensureInstalled();

        $writer = new Writer(new ImageRenderer(new RendererStyle(self::SIZE, 1), new SvgImageBackEnd));

        return $writer->writeString($payload, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());
    }
}
