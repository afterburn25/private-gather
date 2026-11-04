<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Support\TenantUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class GroupHubController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $tenantIds = DB::table('tenant_users')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('tenant_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $groups = CommunityGroup::query()
            ->with(['tenant.primaryDomain', 'tenant.domains'])
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $membershipByGroup = DB::table('community_group_members')
            ->where('user_id', $userId)
            ->whereIn('community_group_id', $groups->pluck('id'))
            ->get()
            ->keyBy('community_group_id');

        $groups->each(function (CommunityGroup $group) use ($membershipByGroup): void {
            $domain = $group->tenant?->primaryDomain ?: $group->tenant?->domains?->first();
            $group->setAttribute('member_open_url', TenantUrl::to($domain, '/community/groups/'.$group->id));
            $group->setAttribute('member_group_status', $membershipByGroup->get($group->id)?->status);
        });

        return view('member.groups-hub', [
            'groups' => $groups,
            'communityCount' => $tenantIds->count(),
        ]);
    }
}
