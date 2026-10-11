<?php

declare(strict_types=1);

namespace Akira\Efatura\Http\Resources;

use Akira\Efatura\Xml\XmlValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property XmlValidation $resource
 */
final class XmlValidationResource extends JsonResource
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
