<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantMember
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->requireTenant();
        $user = $request->user();
        abort_unless($user, 401);

        $membership = $user->tenants()
            ->whereKey($tenant->id)
            ->wherePivot('status', 'active')
            ->first();

        abort_unless($membership, 403, 'This community is available only to active members of this site.');

        $request->attributes->set('community_tenant', $tenant);
        $request->attributes->set('community_membership', $membership->pivot);

        return $next($request);
    }
}
