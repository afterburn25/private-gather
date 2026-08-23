<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityPost;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class GroupController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $manager = $this->tenantManager($request, $tenant->id);

        $groups = CommunityGroup::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->withCount(['members as active_members_count' => fn ($query) => $query->where('community_group_members.status', 'active')])
            ->where(function ($query) use ($request, $manager): void {
                $query->where('visibility', 'tenant');
                if ($manager) {
                    $query->orWhere('visibility', 'invite_only');
                } else {
                    $query->orWhereHas('members', fn ($members) => $members
                        ->where('users.id', $request->user()->id)
                        ->where('community_group_members.status', 'active'));
                }
            })
            ->orderBy('name')
            ->get();

        return view('member.community.groups', [
            'tenant' => $tenant,
            'groups' => $groups,
            'canCreate' => $manager,
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless($this->tenantManager($request, $tenant->id), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'visibility' => ['required', 'in:tenant,invite_only'],
            'join_policy' => ['required', 'in:open,approval,invite_only'],
            'allow_member_posts' => ['nullable', 'boolean'],
        ]);

        $base = Str::slug($data['name']) ?: 'group';
        $slug = $base;
        $suffix = 2;
        while (CommunityGroup::query()->where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $group = CommunityGroup::create([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'visibility' => $data['visibility'],
            'join_policy' => $data['join_policy'],
            'allow_member_posts' => $request->boolean('allow_member_posts'),
            'status' => 'active',
        ]);

        $group->members()->attach($request->user()->id, ['role' => 'moderator', 'status' => 'active']);

        return redirect()->route('community.groups.show', $group)->with('status', 'Group created.');
    }

    public function show(Request $request, CommunityGroup $group, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $this->assertGroupTenant($group, $tenant->id);

        $membership = $this->groupMembership($group, $request->user()->id);
        $manager = $this->tenantManager($request, $tenant->id);
        if ($group->visibility === 'invite_only' && ! $manager && ($membership?->status !== 'active')) {
            abort(404);
        }

        $posts = $group->posts()
            ->where('status', 'active')
            ->with([
                'user:id,name,display_name',
                'comments' => fn ($query) => $query->where('status', 'active')->oldest()->with('user:id,name,display_name'),
                'reactions',
            ])
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(20);

        $members = $group->members()->wherePivot('status', 'active')->orderBy('display_name')->limit(24)->get();
        $pending = collect();
        if ($manager || $membership?->role === 'moderator') {
            $pending = $group->members()->wherePivot('status', 'pending')->orderBy('display_name')->get();
        }

        return view('member.community.group', [
            'tenant' => $tenant,
            'group' => $group,
            'posts' => $posts,
            'members' => $members,
            'pending' => $pending,
            'membership' => $membership,
            'canModerate' => $manager || $membership?->role === 'moderator',
        ]);
    }

    public function join(Request $request, CommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertGroupTenant($group, $tenant->id);
        abort_if($group->join_policy === 'invite_only', 403, 'This group is invite-only.');

        $status = $group->join_policy === 'open' ? 'active' : 'pending';
        $group->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'member', 'status' => $status]]);
        DB::table('community_group_members')
            ->where('community_group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->update(['status' => $status, 'updated_at' => now()]);

        return back()->with('status', $status === 'active' ? 'You joined the group.' : 'Join request sent.');
    }

    public function leave(Request $request, CommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertGroupTenant($group, $tenant->id);
        $group->members()->detach($request->user()->id);

        return redirect()->route('community.groups.index')->with('status', 'You left the group.');
    }

    public function post(Request $request, CommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertGroupTenant($group, $tenant->id);
        $membership = $this->groupMembership($group, $request->user()->id);
        $manager = $this->tenantManager($request, $tenant->id);
        abort_unless($membership?->status === 'active' || $manager, 403);
        abort_unless($group->allow_member_posts || $manager || $membership?->role === 'moderator', 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        CommunityPost::create([
            'tenant_id' => $tenant->id,
            'community_group_id' => $group->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to '.$group->name.'.');
    }

    public function approve(Request $request, CommunityGroup $group, int $user, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertGroupTenant($group, $tenant->id);
        $membership = $this->groupMembership($group, $request->user()->id);
        abort_unless($this->tenantManager($request, $tenant->id) || $membership?->role === 'moderator', 403);

        DB::table('community_group_members')
            ->where('community_group_id', $group->id)
            ->where('user_id', $user)
            ->where('status', 'pending')
            ->update(['status' => 'active', 'updated_at' => now()]);

        return back()->with('status', 'Group member approved.');
    }

    private function assertGroupTenant(CommunityGroup $group, int $tenantId): void
    {
        abort_unless((int) $group->tenant_id === $tenantId && $group->status === 'active', 404);
    }

    private function groupMembership(CommunityGroup $group, int $userId): ?object
    {
        return DB::table('community_group_members')
            ->where('community_group_id', $group->id)
            ->where('user_id', $userId)
            ->first();
    }

    private function tenantManager(Request $request, int $tenantId): bool
    {
        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;
        return (bool) ($membership && in_array($membership->role, ['owner', 'admin', 'manager'], true));
    }
}
