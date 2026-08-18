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
        if (! Edition::isSelfHosted() || Edition::selfHostedVisibility() === 'public' || $request->user()) {
            return $next($request);
        }

        if ($this->isGuestAccessRoute($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        return redirect()->guest(route('login'));
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
