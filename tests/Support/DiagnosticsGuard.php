<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class DiagnosticsGuard
{
    public const string HEADER = 'X-Diagnostics-Token';

    public const string TOKEN = 'diagnostics-test-token';

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->header(self::HEADER) === self::TOKEN, 401);

        return $next($request);
    }
}
