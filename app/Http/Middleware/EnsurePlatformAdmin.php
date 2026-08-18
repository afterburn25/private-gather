<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(TenantContext::class)->check()) {
            abort(404);
        }

        if (! $request->user()) {
            return redirect()->guest(route('admin.login'));
        }

        abort_unless($request->user()->is_platform_admin, 403);

        return $next($request);
    }
}
