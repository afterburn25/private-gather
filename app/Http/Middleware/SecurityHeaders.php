<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Routes whose URLs or response bodies can contain authentication,
     * invitation, recovery, or security state that must never escape through
     * referrers, shared caches, search indexes, or browser previews.
     */
    private const SENSITIVE_ROUTES = [
        'password.reset',
        'password.update',
        'two-factor.challenge',
        'two-factor.challenge.store',
        'verification.verify',
        'staff.invite.accept',
        'event-invitations.show',
        'event-invitations.accept',
        'member.security',
        'member.security.2fa.begin',
        'member.security.2fa.confirm',
        'member.security.2fa.disable',
        'admin.login',
        'admin.login.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Safe baseline headers that do not require a restrictive script/style
        // CSP and therefore do not break Private Gather's existing inline
        // tenant-branding variables or future first-party browser features.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self'; object-src 'none'; base-uri 'self'"
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(), payment=(self), usb=(), serial=()'
        );

        $routeName = $request->route()?->getName();
        $sensitiveRoute = is_string($routeName) && in_array($routeName, self::SENSITIVE_ROUTES, true);

        // Private Gather handles highly sensitive membership/event data. Any
        // authenticated application response should not remain in a shared or
        // persistent browser cache. Token-bearing/security routes get the same
        // protection before authentication is complete.
        if ($request->user() || $sensitiveRoute) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($sensitiveRoute) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        }

        return $response;
    }
}
