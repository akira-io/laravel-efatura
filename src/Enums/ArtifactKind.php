<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ArtifactKind: string
{
    case SignedXml = 'signed_xml';
    case Archive   = 'archive';
    case Response  = 'response';
    case Dfa       = 'dfa';

    public function fileName(string $iud): string
    {
        return str_replace('{iud}', $iud, match ($this) {
            self::SignedXml => '{iud}.xml',
            self::Archive   => '{iud}.zip',
            self::Response  => 'response.json',
            self::Dfa       => '{iud}.pdf',
        });
    }
}
