<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupInvite;
use App\Models\CommunityGroupMember;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\CommunityNotifier;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GroupController extends Controller
{
    public function show(Request $request, CommunityGroup $group, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $this->assertGroupAccess($request, $group, (int) $tenant->id);

        $posts = CommunityPost::query()
            ->where('tenant_id', $tenant->id)
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->with([
                'user:id,name,display_name',
                'comments' => fn ($query) => $query->where('status', 'active')->oldest()->with('user:id,name,display_name'),
                'reactions',
            ])
            ->latest()
            ->paginate(25);

        return view('member.community.context', [
            'tenant' => $tenant,
            'posts' => $posts,
            'contextKind' => 'COMMUNITY GROUP',
            'contextTitle' => $group->name,
            'contextSubtitle' => $group->description ?: 'A private member group inside this club community.',
            'postAction' => route('groups.posts.store', $group),
            'backUrl' => route('network.index'),
            'canModerate' => $this->tenantManager($request, (int) $tenant->id),
        ]);
    }

    public function storePost(Request $request, CommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertGroupAccess($request, $group, (int) $tenant->id, true);
        $membership = $this->groupMembership($group, (int) $request->user()->id);
        abort_unless(
            $membership && ($group->allow_member_posts || in_array((string) $membership->role, ['owner', 'admin', 'moderator'], true)),
            403,
            'Posting is restricted in this group.'
        );
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        CommunityPost::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'group_id' => $group->id,
            'post_type' => 'group',
            'visibility' => 'members',
            'share_to_club_wall' => false,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to the group.');
    }

    public function invite(Request $request, CommunityGroup $group, TenantContext $context, CommunityNotifier $notifier): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertGroupAccess($request, $group, (int) $tenant->id, true);
        abort_unless($this->canManageGroup($request, $group, (int) $tenant->id), 403);

        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $target = User::query()->findOrFail((int) $data['user_id']);
        abort_if((int) $target->id === (int) $request->user()->id, 422, 'You are already in this group.');
        abort_unless($target->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists(), 404);
        abort_if($this->groupMembership($group, (int) $target->id), 422, 'This member is already in the group.');

        $invite = CommunityGroupInvite::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $target->id],
            [
                'invited_by_user_id' => $request->user()->id,
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'responded_at' => null,
            ]
        );

        $notifier->notify((int) $target->id, (int) $tenant->id, 'connection', [
            'title' => 'You were invited to '.$group->name,
            'url' => route('groups.show', $group),
            'group_invite_id' => $invite->id,
        ]);

        return back()->with('status', 'Group invitation sent.');
    }

    public function respondInvite(Request $request, CommunityGroupInvite $invite, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $invite->loadMissing('group');
        $group = $invite->group;
        abort_unless($group && (int) $group->tenant_id === (int) $tenant->id && $group->status === 'active', 404);
        abort_unless((int) $invite->user_id === (int) $request->user()->id, 404);
        abort_unless($invite->status === 'pending', 422, 'This invitation has already been answered.');
        abort_if($invite->expires_at && $invite->expires_at->isPast(), 422, 'This invitation has expired.');

        $data = $request->validate(['action' => ['required', 'in:accept,decline']]);
        if ($data['action'] === 'accept') {
            CommunityGroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $request->user()->id],
                ['role' => 'member', 'status' => 'active']
            );
            $status = 'accepted';
            $message = 'Group invitation accepted.';
        } else {
            $status = 'declined';
            $message = 'Group invitation declined.';
        }

        $invite->update(['status' => $status, 'responded_at' => now()]);

        return redirect()->route('groups.show', $group)->with('status', $message);
    }

    private function assertGroupAccess(Request $request, CommunityGroup $group, int $tenantId, bool $requireMembership = false): void
    {
        abort_unless((int) $group->tenant_id === $tenantId && $group->status === 'active', 404);
        $membership = $this->groupMembership($group, (int) $request->user()->id);

        if ($requireMembership || $group->visibility === 'private') {
            abort_unless($membership, 403, 'This group is available only to its members.');
        }
    }

    private function groupMembership(CommunityGroup $group, int $userId): ?CommunityGroupMember
    {
        return CommunityGroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    private function canManageGroup(Request $request, CommunityGroup $group, int $tenantId): bool
    {
        if ((int) $group->owner_user_id === (int) $request->user()->id || $this->tenantManager($request, $tenantId)) {
            return true;
        }

        $membership = $this->groupMembership($group, (int) $request->user()->id);

        return $membership && in_array((string) $membership->role, ['owner', 'admin', 'moderator'], true);
    }

    private function tenantManager(Request $request, int $tenantId): bool
    {
        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;

        return $membership && in_array((string) $membership->role, ['owner', 'admin', 'manager'], true);
    }
}
