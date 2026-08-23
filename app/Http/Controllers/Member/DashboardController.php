<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\EventRsvp;
use App\Models\MembershipApplication;
use App\Models\PlatformNotification;
use App\Models\PrivateAlbum;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context)
    {
        $tenant = $context->tenant();
        $tenantId = $tenant?->id;
        $user = $request->user()->load('profile');

        if ($tenantId) {
            $user->load([
                'tenants' => fn ($query) => $query
                    ->where('tenants.id', $tenantId)
                    ->with('primaryDomain'),
            ]);
        } else {
            $user->load('tenants.primaryDomain');
        }

        $rsvps = EventRsvp::query()->with('event.tenant')->where('user_id', $user->id)
            ->when($tenantId, fn ($q) => $q->whereHas('event', fn ($event) => $event->where('tenant_id', $tenantId)))
            ->latest()->limit(12)->get();

        $applications = MembershipApplication::query()->where('user_id',$user->id)
            ->when($tenantId, fn($q)=>$q->where('tenant_id',$tenantId))
            ->with('tenant.primaryDomain')->latest()->get();

        $notifications = PlatformNotification::query()->where('user_id',$user->id)
            ->when($tenantId, fn($q)=>$q->where(fn($x)=>$x->whereNull('tenant_id')->orWhere('tenant_id',$tenantId)))
            ->latest()->limit(8)->get();
        $unreadNotifications = $notifications->whereNull('read_at')->count();

        $conversations = Conversation::query()->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))
            ->whereHas('participants',fn($q)=>$q->where('users.id',$user->id))
            ->with(['tenant.primaryDomain','participants:id,name,display_name','messages'=>fn($q)=>$q->whereNull('deleted_at')->latest('id')->limit(1)])
            ->latest('updated_at')->limit(6)->get();

        $posts = CommunityPost::query()->where('user_id',$user->id)->where('status','active')
            ->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))->latest()->limit(6)->get();

        $albums = PrivateAlbum::query()->where('owner_user_id',$user->id)->where('status','active')
            ->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))->withCount('photos')->latest()->limit(6)->get();

        $groupCount = CommunityGroup::query()->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))
            ->whereHas('members',fn($q)=>$q->where('user_id',$user->id)->where('status','active'))->count();

        $connectionCount = DB::table('member_connections')->where('status','accepted')
            ->when($tenantId,fn($q)=>$q->where('tenant_id',$tenantId))
            ->where(fn($q)=>$q->where('user_one_id',$user->id)->orWhere('user_two_id',$user->id))->count();

        return view('member.dashboard', compact(
            'user','tenant','rsvps','applications','notifications','unreadNotifications','conversations','posts','albums','groupCount','connectionCount'
        ));
    }
}
