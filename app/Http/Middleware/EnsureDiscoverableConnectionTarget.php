<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\MemberPrivacy;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDiscoverableConnectionTarget
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MemberPrivacy $privacy,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->requireTenant();
        $viewer = $request->user();
        abort_unless($viewer, 401);

        $target = $request->route('user');
        if (! $target instanceof User) {
            $target = User::query()->findOrFail((int) $target);
        }

        abort_if((int) $viewer->id === (int) $target->id, 422, 'You cannot target yourself.');
        abort_unless(
            $this->privacy->activeMember((int) $tenant->id, (int) $target->id)
            && $this->privacy->canViewProfile((int) $tenant->id, $viewer, $target),
            404
        );

        $request->attributes->set('community_target_user', $target);

        return $next($request);
    }
}
