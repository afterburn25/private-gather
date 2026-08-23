<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureJoinableMemberGroup
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->requireTenant();
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless((bool) data_get($tenant->settings, 'groups_enabled', true), 403, 'Groups are disabled for this club.');

        $group = $request->route('group');
        if (! $group instanceof CommunityGroup) {
            $group = CommunityGroup::query()->findOrFail((int) $group);
        }

        abort_unless((int) $group->tenant_id === (int) $tenant->id && $group->status === 'active', 404);

        $alreadyMember = CommunityGroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $alreadyMember) {
            abort_unless($group->visibility === 'members', 403, 'This private group is invitation-only.');
        }

        $request->attributes->set('community_group', $group);

        return $next($request);
    }
}
