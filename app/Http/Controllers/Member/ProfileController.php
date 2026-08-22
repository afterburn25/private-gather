<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\MemberBadge;
use App\Models\PrivateAlbum;
use App\Models\ProfilePartnerInvite;
use App\Models\User;
use App\Services\CommunityMediaService;
use App\Services\CommunityNotifier;
use App\Services\MemberPrivacy;
use App\Support\Audit;
use App\Support\LifestyleProfileOptions;
use App\Support\UsStates;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request, TenantContext $context): View
    {
        $tenant = $context->tenant();
        $partnerCandidates = collect();
        $partnerInvites = collect();
        if ($tenant) {
            $partnerCandidates = $tenant->users()->wherePivot('status','active')->where('users.id','!=',$request->user()->id)->orderByRaw('COALESCE(display_name,name)')->get(['users.id','users.name','users.display_name']);
            $partnerInvites = ProfilePartnerInvite::query()->where('tenant_id',$tenant->id)->where('recipient_user_id',$request->user()->id)->where('status','pending')->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->with('sender:id,name,display_name')->get();
        }
        return view('member.profile', [
            'profile' => $request->user()->profile?->load('partner:id,name,display_name'),
            'tenant' => $tenant,
            'partnerCandidates' => $partnerCandidates,
            'partnerInvites' => $partnerInvites,
            'lifestyleIdentities' => LifestyleProfileOptions::identities(),
            'relationshipStatuses' => LifestyleProfileOptions::relationshipStatuses(),
            'experienceLevels' => LifestyleProfileOptions::experienceLevels(),
            'lookingForOptions' => LifestyleProfileOptions::lookingFor(),
            'lifestyleInterestOptions' => LifestyleProfileOptions::interests(),
            'visibilityOptions' => LifestyleProfileOptions::visibilityOptions(),
            'usStates' => UsStates::all(),
        ]);
    }

    public function update(Request $request)
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required','string','min:3','max:32','regex:/^[a-z0-9][a-z0-9._-]{2,31}$/',Rule::unique('users','username')->ignore($request->user()->id)],
            'lifestyle_identity' => ['required', Rule::in(array_keys(LifestyleProfileOptions::identities()))],
            'relationship_status' => ['nullable', Rule::in(array_keys(LifestyleProfileOptions::relationshipStatuses()))],
            'experience_level' => ['nullable', Rule::in(array_keys(LifestyleProfileOptions::experienceLevels()))],
            'pronouns' => 'nullable|string|max:80',
            'headline' => 'nullable|string|max:160',
            'bio' => 'nullable|string|max:5000',
            'city' => 'nullable|string|max:120',
            'region' => ['nullable', Rule::in(array_keys(UsStates::all()))],
            'interests' => 'nullable|string|max:1000',
            'looking_for' => 'nullable|array|max:8',
            'looking_for.*' => [Rule::in(array_keys(LifestyleProfileOptions::lookingFor()))],
            'lifestyle_interests' => 'nullable|array|max:16',
            'lifestyle_interests.*' => [Rule::in(array_keys(LifestyleProfileOptions::interests()))],
            'boundaries' => 'nullable|string|max:2500',
            'message_permissions' => 'required|in:members,connections,none',
            'visibility_profile' => 'required|in:members,connections,private',
            'visibility_location' => 'required|in:members,connections,private',
            'visibility_lifestyle' => 'required|in:members,connections,private',
            'visibility_boundaries' => 'required|in:members,connections,private',
            'visibility_photos' => 'required|in:members,connections,private',
        ]);
        $request->user()->update(['username' => $data['username'], 'display_name' => $data['username']]);
        $profile = $request->user()->profile()->firstOrCreate([], ['profile_type' => 'individual']);
        $before = $profile->toArray();
        $profile->update([
            'profile_type' => $data['lifestyle_identity'] === 'couple' ? 'couple' : 'individual',
            'lifestyle_identity' => $data['lifestyle_identity'],
            'relationship_status' => $data['relationship_status'] ?? null,
            'experience_level' => $data['experience_level'] ?? null,
            'pronouns' => $data['pronouns'] ?? null,
            'headline' => $data['headline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'interests' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['interests'] ?? ''))))),
            'looking_for' => array_values($data['looking_for'] ?? []),
            'lifestyle_interests' => array_values($data['lifestyle_interests'] ?? []),
            'boundaries' => $data['boundaries'] ?? null,
            'discoverable' => $request->boolean('discoverable'),
            'message_permissions' => $data['message_permissions'],
            'show_age' => $request->boolean('show_age'),
            'show_last_active' => $request->boolean('show_last_active'),
            'visibility' => [
                'profile' => $data['visibility_profile'],
                'bio' => $data['visibility_profile'],
                'location' => $data['visibility_location'],
                'lifestyle' => $data['visibility_lifestyle'],
                'boundaries' => $data['visibility_boundaries'],
                'photos' => $data['visibility_photos'],
            ],
        ]);
        Audit::write('profile.updated', $profile, $before, $profile->fresh()->toArray(), request: $request);
        return back()->with('status', 'Lifestyle profile and privacy settings updated.');
    }

    public function uploadMedia(Request $request, CommunityMediaService $media): RedirectResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:avatar,cover',
            'media' => 'required|file|max:15360',
        ]);
        $tenantId = (int) ($request->attributes->get('community_tenant')?->id ?? $request->user()->tenants()->wherePivot('status', 'active')->value('tenants.id') ?? 0);
        abort_if($tenantId < 1, 422, 'Join an active club before uploading profile media.');
        $stored = $media->store($data['media'], $tenantId, (int) $request->user()->id, 'profiles');
        abort_unless($stored['type'] === 'image', 422, 'Profile avatar and cover uploads must be images.');
        $profile = $request->user()->profile()->firstOrCreate([], ['profile_type' => 'individual']);
        $column = $data['kind'] === 'avatar' ? 'avatar_path' : 'cover_path';
        if ($profile->{$column}) {
            Storage::disk('local')->delete($profile->{$column});
        }
        $profile->update([$column => $stored['path']]);
        return back()->with('status', ucfirst($data['kind']).' updated.');
    }

    public function show(Request $request, TenantContext $context, User $user, MemberPrivacy $privacy): View
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        abort_unless($privacy->canViewProfile((int) $tenant->id, $viewer, $user), 404);
        $user->loadMissing('profile.partner:id,name,display_name');

        $isMine = (int) $viewer->id === (int) $user->id;
        $isConnected = $isMine ? false : $privacy->connected((int) $tenant->id, (int) $viewer->id, (int) $user->id);

        $wallPosts = CommunityPost::query()
            ->where('tenant_id', $tenant->id)
            ->where('wall_user_id', $user->id)
            ->whereNull('group_id')
            ->whereNull('event_id')
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when(! $isMine, function ($query) use ($isConnected): void {
                $query->where(function ($visibility) use ($isConnected): void {
                    $visibility->where('visibility', 'members');
                    if ($isConnected) {
                        $visibility->orWhere('visibility', 'connections');
                    }
                });
            })
            ->with([
                'user:id,name,display_name',
                'comments' => fn ($q) => $q->where('status','active')->whereNull('parent_id')->oldest()->with(['user:id,name,display_name','children' => fn ($child) => $child->where('status','active')->with('user:id,name,display_name')]),
                'reactions',
            ])
            ->latest()
            ->paginate(15);

        $canViewPhotos = $isMine || $privacy->fieldVisible($user->profile, 'photos', (int) $tenant->id, (int) $viewer->id, (int) $user->id);
        $albums = $canViewPhotos
            ? PrivateAlbum::query()
                ->where('tenant_id', $tenant->id)
                ->where('owner_user_id', $user->id)
                ->where('status', 'active')
                ->with(['photos' => fn ($q) => $q->orderBy('sort_order')->limit(8)])
                ->latest()
                ->get()
                ->filter(function (PrivateAlbum $album) use ($viewer, $privacy, $tenant, $user, $isMine, $isConnected): bool {
                    if ($isMine) return true;
                    return match ($album->visibility) {
                        'members' => true,
                        'connections' => $isConnected,
                        'granted' => $album->grants()->where('user_id', $viewer->id)->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists(),
                        default => false,
                    };
                })->values()
            : collect();

        $badges = MemberBadge::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when(! $isMine, function ($query) use ($isConnected): void {
                $query->where(function ($visibility) use ($isConnected): void {
                    $visibility->where('visibility', 'members');
                    if ($isConnected) $visibility->orWhere('visibility', 'connections');
                });
            })->get();

        return view('member.profile-show', [
            'tenant' => $tenant,
            'member' => $user,
            'profile' => $user->profile,
            'viewer' => $viewer,
            'wallPosts' => $wallPosts,
            'albums' => $albums,
            'badges' => $badges,
            'privacy' => $privacy,
            'canMessage' => $privacy->canMessage((int) $tenant->id, $viewer, $user),
            'isConnected' => $isConnected,
        ]);
    }

    public function ownMedia(Request $request, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind,['avatar','cover'],true),404);
        $request->user()->loadMissing('profile');
        $path=$kind==='avatar'?$request->user()->profile?->avatar_path:$request->user()->profile?->cover_path;
        abort_unless($path && Storage::disk('local')->exists($path),404);
        return Storage::disk('local')->response($path,null,['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private,no-store']);
    }

    public function media(Request $request, TenantContext $context, User $user, string $kind, MemberPrivacy $privacy): StreamedResponse
    {
        $tenant = $context->requireTenant();
        abort_unless(in_array($kind, ['avatar', 'cover'], true), 404);
        abort_unless($privacy->canViewProfile((int) $tenant->id, $request->user(), $user), 404);
        $user->loadMissing('profile');
        $path = $kind === 'avatar' ? $user->profile?->avatar_path : $user->profile?->cover_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private,no-store',
        ]);
    }

    public function invitePartner(Request $request, TenantContext $context, CommunityNotifier $notifier): RedirectResponse
    {
        $tenant=$context->requireTenant();
        $data=$request->validate(['partner_user_id'=>'required|integer|exists:users,id']);
        $target=User::findOrFail((int)$data['partner_user_id']);
        abort_if((int)$target->id===(int)$request->user()->id,422);
        abort_unless($tenant->users()->whereKey($target->id)->wherePivot('status','active')->exists(),404);
        $request->user()->loadMissing('profile');
        abort_if($request->user()->profile?->partner_user_id,422,'Unlink your current partner before sending another request.');
        ProfilePartnerInvite::updateOrCreate(
            ['tenant_id'=>$tenant->id,'sender_user_id'=>$request->user()->id,'recipient_user_id'=>$target->id],
            ['status'=>'pending','expires_at'=>now()->addDays(14),'responded_at'=>null]
        );
        $notifier->notify((int)$target->id,(int)$tenant->id,'connection',['title'=>($request->user()->display_name?:$request->user()->name).' invited you to link partner profiles','url'=>route('profile.edit')]);
        return back()->with('status','Partner-link invitation sent.');
    }

    public function respondPartner(Request $request, TenantContext $context, ProfilePartnerInvite $invite): RedirectResponse
    {
        $tenant=$context->requireTenant();
        abort_unless((int)$invite->tenant_id===(int)$tenant->id && (int)$invite->recipient_user_id===(int)$request->user()->id,404);
        abort_unless($invite->status==='pending' && (!$invite->expires_at || $invite->expires_at->isFuture()),422,'This partner invitation is no longer available.');
        $data=$request->validate(['decision'=>'required|in:accept,decline']);
        if($data['decision']==='decline'){ $invite->update(['status'=>'declined','responded_at'=>now()]); return back()->with('status','Partner invitation declined.'); }
        $sender=User::findOrFail($invite->sender_user_id);
        abort_unless($tenant->users()->whereKey($sender->id)->wherePivot('status','active')->exists(),404);
        DB::transaction(function()use($request,$sender,$invite):void{
            $mine=$request->user()->profile()->firstOrCreate([],['profile_type'=>'individual']);
            $theirs=$sender->profile()->firstOrCreate([],['profile_type'=>'individual']);
            abort_if($mine->partner_user_id || $theirs->partner_user_id,422,'One of these profiles is already linked to a partner.');
            $mine->update(['partner_user_id'=>$sender->id,'profile_type'=>'couple']);
            $theirs->update(['partner_user_id'=>$request->user()->id,'profile_type'=>'couple']);
            $invite->update(['status'=>'accepted','responded_at'=>now()]);
        });
        return back()->with('status','Partner profiles linked. Each person keeps their own login, messages and privacy settings.');
    }

    public function unlinkPartner(Request $request): RedirectResponse
    {
        $profile=$request->user()->profile;
        abort_unless($profile?->partner_user_id,422,'No partner profile is linked.');
        $partnerId=(int)$profile->partner_user_id;
        DB::transaction(function()use($profile,$partnerId):void{
            $profile->update(['partner_user_id'=>null]);
            DB::table('profiles')->where('user_id',$partnerId)->where('partner_user_id',$profile->user_id)->update(['partner_user_id'=>null,'updated_at'=>now()]);
        });
        return back()->with('status','Partner profiles unlinked.');
    }

    public function block(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_if((int) $user->id === (int) $request->user()->id, 422);
        abort_unless(DB::table('tenant_users')->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists(), 404);
        DB::table('user_blocks')->updateOrInsert(
            ['user_id' => $request->user()->id, 'blocked_user_id' => $user->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        return redirect()->route('community.index')->with('status', 'Member blocked. They can no longer message you or view your profile.');
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        DB::table('user_blocks')->where('user_id', $request->user()->id)->where('blocked_user_id', $user->id)->delete();
        return back()->with('status', 'Member unblocked.');
    }
}
