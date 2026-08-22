<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DemoEnvironmentHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoMode::enabled()) {
            return $next($request);
        }

        $expectedHost = DemoMode::host();
        $actualHost = strtolower($request->getHost());
        $hostAllowed = $expectedHost !== '' && (
            hash_equals($expectedHost, $actualHost)
            || str_ends_with($actualHost, '.'.$expectedHost)
        );

        if (! $hostAllowed) {
            return response('Private Gather demo host configuration mismatch.', 421, [
                'X-Private-Gather-Environment' => 'demo',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        }

        $response = $next($request);
        $response->headers->set('X-Private-Gather-Environment', 'demo');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
