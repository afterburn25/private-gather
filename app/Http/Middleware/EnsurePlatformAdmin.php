<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        // Hosted tenant domains must never expose or redirect into the central
        // platform-administration surface, regardless of authentication state.
        if (Edition::isHosted() && $context->check()) {
            abort(404);
        }

        if (! $request->user()) {
            return redirect()->guest(route('admin.login'));
        }

        abort_unless($request->user()->is_platform_admin, 403);

        if (Edition::isSelfHosted()) {
            $tenant = $context->requireTenant();
            $membership = $request->user()->tenants()->whereKey($tenant->id)->first()?->pivot;

            abort_unless(
                $membership
                && $membership->status === 'active'
                && in_array($membership->role, ['owner', 'admin'], true),
                403
            );

            if ($request->routeIs('admin.tenants.*', 'admin.plans.*', 'admin.content.*')) {
                abort(404);
            }

            return $next($request);
        }

        return $next($request);
    }
}
