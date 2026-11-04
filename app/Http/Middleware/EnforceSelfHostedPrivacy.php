<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSelfHostedPrivacy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Edition::isSelfHosted() || Edition::selfHostedVisibility() === 'public') {
            return $next($request);
        }

        if ($request->user() || $this->isGuestAccessRoute($request)) {
            return $this->privateResponse($next($request));
        }

        if ($request->expectsJson()) {
            return $this->privateResponse(response()->json(['message' => 'Authentication required.'], 401));
        }

        return $this->privateResponse(redirect()->guest(route('login')));
    }

    private function privateResponse(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    private function isGuestAccessRoute(Request $request): bool
    {
        if ($request->routeIs(
            'login',
            'login.store',
            'admin.login',
            'admin.login.store',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'two-factor.challenge',
            'two-factor.challenge.store',
            'health',
        )) {
            return true;
        }

        return Edition::registrationEnabled()
            && $request->routeIs('register', 'register.store');
    }
}
