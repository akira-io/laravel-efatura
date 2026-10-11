<?php

declare(strict_types=1);

namespace Akira\Efatura\Http\Resources;

use Akira\Efatura\Readiness\ReadinessReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property ReadinessReport $resource
 */
final class ReadinessResource extends JsonResource
{
    /** @var string|null */
    #[Override]
    public static $wrap;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
