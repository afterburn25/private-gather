<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->status === 'active') {
            return $next($request);
        }

        // Account status changes must take effect for already-issued sessions.
        // Checking status only during login would let a suspended or banned
        // account keep using an existing authenticated browser session.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(403, 'This account is not active.');
        }

        return redirect('/login')->withErrors([
            'email' => 'This account is not currently available.',
        ]);
    }
}
