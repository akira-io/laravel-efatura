<?php

declare(strict_types=1);

namespace Akira\Efatura\Http\Middleware;

use Akira\Efatura\Configuration\RoutesConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class EnsureDiagnosticsRoutesEnabled
{
    public function __construct(private RoutesConfig $routes) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->routes->enabled) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
