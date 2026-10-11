<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use BaconQrCode\Writer;
use Dompdf\Dompdf;

final readonly class DfaLibraries
{
    /**
     * @param list<LibraryRequirement> $requirements
     */
    public function __construct(
        public array $requirements = [
            new LibraryRequirement('dompdf/dompdf', Dompdf::class),
            new LibraryRequirement('bacon/bacon-qr-code', Writer::class),
        ],
    ) {}

    /**
     * @return list<string>
     */
    public function missing(): array
    {
        return array_values(collect($this->requirements)
            ->reject(static fn (LibraryRequirement $requirement): bool => $requirement->installed())
            ->map(static fn (LibraryRequirement $requirement): string => $requirement->package)
            ->all());
    }
}
