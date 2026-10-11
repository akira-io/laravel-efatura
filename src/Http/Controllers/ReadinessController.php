<?php

declare(strict_types=1);

namespace Akira\Efatura\Http\Controllers;

use Akira\Efatura\Actions\CheckReadinessAction;
use Akira\Efatura\Configuration\RoutesConfig;
use Akira\Efatura\Http\Resources\ReadinessResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReadinessController
{
    public function __invoke(CheckReadinessAction $readiness, RoutesConfig $routes): JsonResponse
    {
        $report = $readiness->handle($routes->remoteReadiness);

        return new ReadinessResource($report)->response()->setStatusCode($report->ready() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
