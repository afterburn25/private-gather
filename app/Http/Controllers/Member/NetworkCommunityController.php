<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\NetworkCommunityComment;
use App\Models\NetworkCommunityGroup;
use App\Models\NetworkCommunityPost;
use App\Models\NetworkCommunityReaction;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class NetworkCommunityController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $this->requireCentralHosted($context);
        $blocked = $this->blockedUserIds((int) $request->user()->id);

        $posts = $this->visiblePosts($blocked)
            ->whereNull('network_community_group_id')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(25);

        $groups = NetworkCommunityGroup::query()
            ->where('status', 'active')
            ->latest()
            ->limit(6)
            ->get();

        return view('member.network-community.index', [
            'posts' => $posts,
            'groups' => $groups,
            'canModerate' => (bool) $request->user()->is_platform_admin,
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        NetworkCommunityPost::create([
            'network_community_group_id' => null,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to the Private Gather community.');
    }

    public function comment(Request $request, NetworkCommunityPost $post, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $this->assertPostAccess($request, $post);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        NetworkCommunityComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back();
    }

    public function react(Request $request, NetworkCommunityPost $post, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $this->assertPostAccess($request, $post);
        $data = $request->validate(['reaction' => ['required', 'in:like,love,celebrate,support']]);

        NetworkCommunityReaction::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            ['reaction' => $data['reaction']]
        );

        return back();
    }

    public function removeReaction(Request $request, NetworkCommunityPost $post, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $this->assertPostAccess($request, $post);
        NetworkCommunityReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back();
    }

    public function destroy(Request $request, NetworkCommunityPost $post, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $this->assertPostAccess($request, $post);
        abort_unless($this->canModeratePost($request, $post) || (int) $post->user_id === (int) $request->user()->id, 403);
        $post->update(['status' => 'removed']);

        return back()->with('status', 'Community post removed.');
    }

    public function destroyComment(Request $request, NetworkCommunityComment $comment, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $post = NetworkCommunityPost::query()->findOrFail($comment->post_id);
        $this->assertPostAccess($request, $post);
        abort_unless($this->canModeratePost($request, $post) || (int) $comment->user_id === (int) $request->user()->id, 403);
        $comment->update(['status' => 'removed']);

        return back();
    }

    public function pin(Request $request, NetworkCommunityPost $post, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $this->assertPostAccess($request, $post);
        abort_unless($this->canModeratePost($request, $post), 403);
        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back();
    }

    public function groups(Request $request, TenantContext $context): View
    {
        $this->requireCentralHosted($context);
        $memberships = DB::table('network_community_group_members')
            ->where('user_id', $request->user()->id)
            ->pluck('status', 'network_community_group_id');

        return view('member.network-community.groups', [
            'groups' => NetworkCommunityGroup::query()->where('status', 'active')->latest()->paginate(30),
            'memberships' => $memberships,
        ]);
    }

    public function storeGroup(Request $request, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'join_policy' => ['required', 'in:open,approval'],
        ]);

        $group = DB::transaction(function () use ($request, $data): NetworkCommunityGroup {
            $base = Str::slug($data['name']);
            $base = $base !== '' ? $base : 'group';
            $slug = $base;
            $counter = 2;
            while (NetworkCommunityGroup::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$counter++;
            }

            $group = NetworkCommunityGroup::create([
                'created_by' => $request->user()->id,
                'name' => trim($data['name']),
                'slug' => $slug,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'join_policy' => $data['join_policy'],
                'status' => 'active',
            ]);

            DB::table('network_community_group_members')->insert([
                'network_community_group_id' => $group->id,
                'user_id' => $request->user()->id,
                'role' => 'owner',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $group;
        });

        return redirect()->route('network-community.groups.show', $group)->with('status', 'Community group created.');
    }

    public function showGroup(Request $request, NetworkCommunityGroup $group, TenantContext $context): View
    {
        $this->requireCentralHosted($context);
        abort_unless($group->status === 'active', 404);

        $membership = DB::table('network_community_group_members')
            ->where('network_community_group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->first();
        $isMember = $membership && $membership->status === 'active';
        $blocked = $this->blockedUserIds((int) $request->user()->id);

        $posts = $isMember
            ? $this->visiblePosts($blocked)
                ->where('network_community_group_id', $group->id)
                ->orderByDesc('is_pinned')
                ->latest()
                ->paginate(25)
            : NetworkCommunityPost::query()->whereRaw('1 = 0')->paginate(25);

        $pendingMembers = collect();
        if ($this->canModerateGroup($request, $group->id)) {
            $pendingMembers = DB::table('network_community_group_members as gm')
                ->join('users as u', 'u.id', '=', 'gm.user_id')
                ->where('gm.network_community_group_id', $group->id)
                ->where('gm.status', 'pending')
                ->orderBy('gm.created_at')
                ->get(['u.id', 'u.name', 'u.display_name']);
        }

        return view('member.network-community.group', [
            'group' => $group,
            'membership' => $membership,
            'isMember' => $isMember,
            'posts' => $posts,
            'pendingMembers' => $pendingMembers,
            'canModerate' => $this->canModerateGroup($request, $group->id),
        ]);
    }

    public function joinGroup(Request $request, NetworkCommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        abort_unless($group->status === 'active', 404);
        $status = $group->join_policy === 'open' ? 'active' : 'pending';

        DB::table('network_community_group_members')->updateOrInsert(
            ['network_community_group_id' => $group->id, 'user_id' => $request->user()->id],
            ['role' => 'member', 'status' => $status, 'created_at' => now(), 'updated_at' => now()]
        );

        return back()->with('status', $status === 'active' ? 'You joined the group.' : 'Join request submitted for approval.');
    }

    public function leaveGroup(Request $request, NetworkCommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        $membership = DB::table('network_community_group_members')
            ->where('network_community_group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($membership, 404);
        abort_if($membership->role === 'owner', 422, 'Transfer or close the group before the owner leaves.');

        DB::table('network_community_group_members')
            ->where('network_community_group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return redirect()->route('network-community.groups.index')->with('status', 'You left the group.');
    }

    public function postGroup(Request $request, NetworkCommunityGroup $group, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        abort_unless($group->status === 'active' && $this->activeGroupMember($group->id, (int) $request->user()->id), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        NetworkCommunityPost::create([
            'network_community_group_id' => $group->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to the group.');
    }

    public function approveGroupMember(Request $request, NetworkCommunityGroup $group, int $user, TenantContext $context): RedirectResponse
    {
        $this->requireCentralHosted($context);
        abort_unless($this->canModerateGroup($request, $group->id), 403);

        $updated = DB::table('network_community_group_members')
            ->where('network_community_group_id', $group->id)
            ->where('user_id', $user)
            ->where('status', 'pending')
            ->update(['status' => 'active', 'updated_at' => now()]);
        abort_unless($updated === 1, 404);

        return back()->with('status', 'Group member approved.');
    }

    private function visiblePosts(array $blocked): Builder
    {
        return NetworkCommunityPost::query()
            ->where('status', 'active')
            ->when($blocked !== [], fn (Builder $query) => $query->where(function (Builder $inner) use ($blocked): void {
                $inner->whereNull('user_id')->orWhereNotIn('user_id', $blocked);
            }))
            ->with([
                'user:id,name,display_name',
                'comments' => fn ($query) => $query
                    ->where('status', 'active')
                    ->when($blocked !== [], fn ($inner) => $inner->where(function ($safe) use ($blocked): void {
                        $safe->whereNull('user_id')->orWhereNotIn('user_id', $blocked);
                    }))
                    ->oldest()
                    ->with('user:id,name,display_name'),
                'reactions',
            ]);
    }

    private function assertPostAccess(Request $request, NetworkCommunityPost $post): void
    {
        abort_unless($post->status === 'active', 404);
        if ($post->network_community_group_id === null) {
            return;
        }
        if ((bool) $request->user()->is_platform_admin) {
            return;
        }
        abort_unless($this->activeGroupMember((int) $post->network_community_group_id, (int) $request->user()->id), 404);
    }

    private function canModeratePost(Request $request, NetworkCommunityPost $post): bool
    {
        if ((bool) $request->user()->is_platform_admin) {
            return true;
        }
        return $post->network_community_group_id !== null
            && $this->canModerateGroup($request, (int) $post->network_community_group_id);
    }

    private function canModerateGroup(Request $request, int $groupId): bool
    {
        if ((bool) $request->user()->is_platform_admin) {
            return true;
        }

        return DB::table('network_community_group_members')
            ->where('network_community_group_id', $groupId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'moderator'])
            ->exists();
    }

    private function activeGroupMember(int $groupId, int $userId): bool
    {
        return DB::table('network_community_group_members')
            ->where('network_community_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function blockedUserIds(int $userId): array
    {
        return DB::table('user_blocks')
            ->where('user_id', $userId)
            ->pluck('blocked_user_id')
            ->merge(DB::table('user_blocks')->where('blocked_user_id', $userId)->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function requireCentralHosted(TenantContext $context): void
    {
        abort_unless(Edition::isHosted() && ! $context->check(), 404);
    }
}
