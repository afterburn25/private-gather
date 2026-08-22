<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\MemberConnection;
use App\Models\PrivateAlbum;
use App\Models\PrivateAlbumAccessGrant;
use App\Models\PrivateAlbumPhoto;
use App\Models\TravelPlan;
use App\Models\User;
use App\Services\CommunityMediaService;
use App\Services\CommunityNotifier;
use App\Services\ImageSanitizer;
use App\Services\MemberPrivacy;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HostedNetworkController extends Controller
{
    private const MAX_IMAGE_PIXELS = 40_000_000;
    private const MAX_IMAGE_EDGE = 12_000;

    public function index(Request $request, TenantContext $context, MemberPrivacy $privacy): View
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        $connections = MemberConnection::query()
            ->where('tenant_id', $tenant->id)
            ->where(fn ($query) => $query->where('user_one_id', $viewer->id)->orWhere('user_two_id', $viewer->id))
            ->with(['userOne:id,name,display_name', 'userTwo:id,name,display_name', 'requestedBy:id,name,display_name'])
            ->latest()
            ->get();

        $connectedIds = $connections
            ->where('status', 'accepted')
            ->map(fn (MemberConnection $connection) => (int) ($connection->user_one_id === $viewer->id ? $connection->user_two_id : $connection->user_one_id))
            ->values();

        $directory = (bool) data_get($tenant->settings, 'member_directory', true)
            ? $tenant->users()
            ->wherePivot('status', 'active')
            ->where('users.id', '<>', $viewer->id)
            ->with('profile:user_id,city,region,headline,discoverable,lifestyle_identity,relationship_status,experience_level,looking_for,lifestyle_interests,avatar_path,visibility,message_permissions')
            ->orderBy('display_name')
            ->orderBy('name')
            ->limit(100)
            ->get(['users.id', 'users.name', 'users.display_name'])
            ->filter(fn (User $member) => $privacy->canViewProfile((int) $tenant->id, $viewer, $member))
            ->values()
            : collect();

        $ownedAlbums = PrivateAlbum::query()
            ->where('tenant_id', $tenant->id)
            ->where('owner_user_id', $viewer->id)
            ->where('status', 'active')
            ->withCount(['photos', 'grants'])
            ->latest()
            ->get();

        $sharedAlbums = PrivateAlbum::query()
            ->where('tenant_id', $tenant->id)
            ->where('owner_user_id', '<>', $viewer->id)
            ->where('status', 'active')
            ->with(['owner:id,name,display_name', 'owner.profile:user_id,visibility'])
            ->withCount('photos')
            ->get()
            ->filter(function (PrivateAlbum $album) use ($viewer, $connectedIds, $privacy, $tenant): bool {
                if (! $album->owner || $privacy->blockedEitherDirection((int) $viewer->id, (int) $album->owner_user_id)) return false;
                if (! $privacy->fieldVisible($album->owner->profile, 'photos', (int) $tenant->id, (int) $viewer->id, (int) $album->owner_user_id)) return false;
                return $this->canViewAlbum($album, (int) $viewer->id, $connectedIds->all());
            })
            ->values();

        $myGroupIds = CommunityGroupMember::query()
            ->where('user_id', $viewer->id)
            ->where('status', 'active')
            ->pluck('group_id')
            ->all();
        $groups = CommunityGroup::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where(function ($query) use ($myGroupIds): void {
                $query->where('visibility', 'members');
                if ($myGroupIds !== []) {
                    $query->orWhereIn('id', $myGroupIds);
                }
            })
            ->with(['owner:id,name,display_name'])
            ->withCount(['members' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->get();

        $travelPlans = TravelPlan::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('ends_on', '>=', now()->toDateString())
            ->with('user:id,name,display_name')
            ->orderBy('starts_on')
            ->get()
            ->filter(function (TravelPlan $plan) use ($viewer, $connectedIds, $privacy): bool {
                if ((int) $plan->user_id === (int) $viewer->id) {
                    return true;
                }
                if ($privacy->blockedEitherDirection((int) $viewer->id, (int) $plan->user_id)) {
                    return false;
                }
                if ($plan->visibility === 'members') {
                    return true;
                }
                return $plan->visibility === 'connections' && $connectedIds->contains((int) $plan->user_id);
            })
            ->values();

        return view('member.network.index', compact(
            'tenant', 'viewer', 'connections', 'connectedIds', 'directory', 'ownedAlbums',
            'sharedAlbums', 'groups', 'myGroupIds', 'travelPlans', 'privacy'
        ));
    }

    public function requestConnection(Request $request, TenantContext $context, User $user, CommunityNotifier $notifier): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        abort_if((int) $viewer->id === (int) $user->id, 422, 'You cannot connect with yourself.');
        abort_unless($this->isActiveTenantMember($tenant->id, $user->id), 404);
        [$one, $two] = MemberConnection::orderedPair((int) $viewer->id, (int) $user->id);

        $connection = MemberConnection::firstOrNew([
            'tenant_id' => $tenant->id,
            'user_one_id' => $one,
            'user_two_id' => $two,
        ]);
        abort_if($connection->exists && in_array($connection->status, ['pending', 'accepted'], true), 422, 'A connection already exists or is pending.');
        $connection->fill([
            'requested_by_user_id' => $viewer->id,
            'status' => 'pending',
            'accepted_at' => null,
        ])->save();

        $notifier->notify((int) $user->id, (int) $tenant->id, 'connection', ['title' => ($viewer->display_name ?: $viewer->name).' sent you a connection request', 'url' => route('network.index').'#connections']);

        return back()->with('status', 'Connection request sent.');
    }

    public function acceptConnection(Request $request, TenantContext $context, MemberConnection $connection, CommunityNotifier $notifier): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        abort_unless((int) $connection->tenant_id === (int) $tenant->id, 404);
        abort_unless(in_array((int) $viewer->id, [(int) $connection->user_one_id, (int) $connection->user_two_id], true), 404);
        abort_unless($connection->status === 'pending' && (int) $connection->requested_by_user_id !== (int) $viewer->id, 422, 'This request cannot be accepted.');
        $connection->update(['status' => 'accepted', 'accepted_at' => now()]);
        $otherId = (int) ($connection->user_one_id === $viewer->id ? $connection->user_two_id : $connection->user_one_id);
        $notifier->notify($otherId, (int) $tenant->id, 'connection', ['title' => ($viewer->display_name ?: $viewer->name).' accepted your connection request', 'url' => route('members.show', $viewer)]);

        return back()->with('status', 'Connection accepted.');
    }

    public function removeConnection(Request $request, TenantContext $context, MemberConnection $connection): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        abort_unless((int) $connection->tenant_id === (int) $tenant->id, 404);
        abort_unless(in_array((int) $viewer->id, [(int) $connection->user_one_id, (int) $connection->user_two_id], true), 404);
        $connection->delete();

        return back()->with('status', 'Connection removed.');
    }

    public function createAlbum(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:3000',
            'visibility' => 'required|in:private,granted,connections,members',
        ]);
        PrivateAlbum::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'],
            'status' => 'active',
        ]);

        return back()->with('status', 'Private album created.');
    }

    public function uploadAlbumPhoto(Request $request, TenantContext $context, PrivateAlbum $album, CommunityMediaService $media): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        $this->assertOwnedAlbum($album, $tenant->id, $viewer->id);
        $data = $request->validate(['file' => 'required|file|max:204800', 'caption' => 'nullable|string|max:255']);
        if (str_starts_with(strtolower((string) $data['file']->getMimeType()), 'video/')) {
            abort_unless((bool) data_get($tenant->settings, 'member_video_uploads', true), 403, 'Member video uploads are disabled for this club.');
        }
        $stored = $media->store($data['file'], (int) $tenant->id, (int) $viewer->id, 'albums/'.$album->id);
        $album->photos()->create([
            'path' => $stored['path'],
            'media_type' => $stored['type'],
            'mime_type' => $stored['mime'],
            'size_bytes' => $stored['size'],
            'duration_seconds' => $stored['duration_seconds'],
            'caption' => $data['caption'] ?? null,
            'sort_order' => ((int) $album->photos()->max('sort_order')) + 10,
        ]);
        return back()->with('status', ucfirst($stored['type']).' added to your private album.');
    }

    public function showAlbumPhoto(Request $request, TenantContext $context, PrivateAlbumPhoto $photo, MemberPrivacy $privacy): StreamedResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        $photo->load('album.owner.profile');
        $album = $photo->album;
        abort_unless($album && (int) $album->tenant_id === (int) $tenant->id && $album->status === 'active', 404);
        abort_unless($album->owner && ! $privacy->blockedEitherDirection((int) $viewer->id, (int) $album->owner_user_id), 404);
        if ((int) $album->owner_user_id !== (int) $viewer->id) {
            abort_unless($privacy->fieldVisible($album->owner->profile, 'photos', (int) $tenant->id, (int) $viewer->id, (int) $album->owner_user_id), 403);
        }
        abort_unless($this->canViewAlbum($album, (int) $viewer->id), 403);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, [
            'Content-Type' => $photo->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function grantAlbum(Request $request, TenantContext $context, PrivateAlbum $album): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        $this->assertOwnedAlbum($album, $tenant->id, $viewer->id);
        $data = $request->validate(['user_id' => 'required|integer', 'expires_at' => 'nullable|date|after:now']);
        $targetId = (int) $data['user_id'];
        abort_if($targetId === (int) $viewer->id, 422, 'You already own this album.');
        abort_unless($this->isActiveTenantMember($tenant->id, $targetId), 404);
        PrivateAlbumAccessGrant::updateOrCreate(
            ['album_id' => $album->id, 'user_id' => $targetId],
            ['granted_by_user_id' => $viewer->id, 'expires_at' => $data['expires_at'] ?? null, 'revoked_at' => null]
        );

        return back()->with('status', 'Album access granted.');
    }

    public function revokeAlbum(Request $request, TenantContext $context, PrivateAlbum $album, PrivateAlbumAccessGrant $grant): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        $this->assertOwnedAlbum($album, $tenant->id, $viewer->id);
        abort_unless((int) $grant->album_id === (int) $album->id, 404);
        $grant->update(['revoked_at' => now()]);

        return back()->with('status', 'Album access revoked.');
    }

    public function createGroup(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((bool) data_get($tenant->settings, 'groups_enabled', true), 403, 'Groups are disabled for this club.');
        $viewer = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:3000',
            'visibility' => 'required|in:members,private',
            'group_type' => 'nullable|in:interest,travel,event,education,social,vip,new_members',
        ]);
        $base = Str::slug($data['name']) ?: 'group';
        $slug = $base;
        $suffix = 2;
        while (CommunityGroup::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        $group = CommunityGroup::create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $viewer->id,
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'group_type' => $data['group_type'] ?? 'interest',
            'visibility' => $data['visibility'],
            'allow_member_posts' => $request->boolean('allow_member_posts', true),
            'status' => 'active',
        ]);
        CommunityGroupMember::create(['group_id' => $group->id, 'user_id' => $viewer->id, 'role' => 'owner', 'status' => 'active']);

        return back()->with('status', 'Community group created.');
    }

    public function joinGroup(Request $request, TenantContext $context, CommunityGroup $group): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((bool) data_get($tenant->settings, 'groups_enabled', true), 403, 'Groups are disabled for this club.');
        abort_unless((int) $group->tenant_id === (int) $tenant->id && $group->status === 'active', 404);
        abort_unless($group->visibility === 'members', 403, 'This private group is invitation-only.');
        CommunityGroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $request->user()->id],
            ['role' => 'member', 'status' => 'active']
        );

        return back()->with('status', 'Joined group.');
    }

    public function leaveGroup(Request $request, TenantContext $context, CommunityGroup $group): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $group->tenant_id === (int) $tenant->id, 404);
        abort_if((int) $group->owner_user_id === (int) $request->user()->id, 422, 'The group owner cannot leave without transferring or closing the group.');
        CommunityGroupMember::where('group_id', $group->id)->where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'Left group.');
    }

    public function createTravelPlan(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'city' => 'required|string|max:120', 'region' => 'nullable|string|max:120', 'country' => 'nullable|string|max:120',
            'starts_on' => 'required|date|after_or_equal:today', 'ends_on' => 'required|date|after_or_equal:starts_on',
            'visibility' => 'required|in:private,connections,members', 'notes' => 'nullable|string|max:3000',
        ]);
        TravelPlan::create([
            'tenant_id' => $tenant->id, 'user_id' => $request->user()->id,
            'city' => trim($data['city']), 'region' => $data['region'] ?? null, 'country' => $data['country'] ?? null,
            'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'visibility' => $data['visibility'],
            'notes' => $data['notes'] ?? null, 'status' => 'active',
        ]);

        return back()->with('status', 'Travel plan posted.');
    }

    public function deleteTravelPlan(Request $request, TenantContext $context, TravelPlan $plan): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $plan->tenant_id === (int) $tenant->id && (int) $plan->user_id === (int) $request->user()->id, 404);
        $plan->delete();

        return back()->with('status', 'Travel plan removed.');
    }

    private function assertOwnedAlbum(PrivateAlbum $album, int $tenantId, int $userId): void
    {
        abort_unless((int) $album->tenant_id === $tenantId && (int) $album->owner_user_id === $userId && $album->status === 'active', 404);
    }

    private function isActiveTenantMember(int $tenantId, int $userId): bool
    {
        return User::query()->whereKey($userId)->whereHas('tenants', function ($query) use ($tenantId): void {
            $query->where('tenants.id', $tenantId)->where('tenant_users.status', 'active');
        })->exists();
    }

    private function canViewAlbum(PrivateAlbum $album, int $viewerId, ?array $knownConnectedIds = null): bool
    {
        if ((int) $album->owner_user_id === $viewerId) {
            return true;
        }
        if ($album->visibility === 'members') {
            return true;
        }
        if ($album->visibility === 'private') {
            return false;
        }
        if ($album->visibility === 'granted') {
            return $album->grants()
                ->where('user_id', $viewerId)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        }
        if ($album->visibility === 'connections') {
            if ($knownConnectedIds !== null) {
                return in_array((int) $album->owner_user_id, array_map('intval', $knownConnectedIds), true);
            }
            [$one, $two] = MemberConnection::orderedPair((int) $album->owner_user_id, $viewerId);
            return MemberConnection::query()
                ->where('tenant_id', $album->tenant_id)
                ->where('user_one_id', $one)
                ->where('user_two_id', $two)
                ->where('status', 'accepted')
                ->exists();
        }

        return false;
    }
}
