<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\NetworkCommunityPost;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventDiscussionController extends Controller
{
    public function tenant(Request $request, TenantContext $context, Event $event)
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $event->tenant_id === (int) $tenant->id && $event->status !== 'cancelled', 404);
        $blocked = $this->blockedIds((int) $request->user()->id);
        return view('member.community.event-discussion', [
            'scope' => 'tenant',
            'tenant' => $tenant,
            'event' => $event,
            'posts' => CommunityPost::query()
                ->where('tenant_id', $tenant->id)->where('event_id', $event->id)->whereNull('community_group_id')->where('status', 'active')
                ->when($blocked !== [], fn ($q) => $q->whereNotIn('user_id', $blocked))
                ->with(['user:id,name,display_name','comments' => fn ($q) => $q->where('status','active')->when($blocked !== [], fn ($c) => $c->whereNotIn('user_id',$blocked))->oldest()->with('user:id,name,display_name'),'reactions'])
                ->orderByDesc('is_pinned')->latest()->paginate(25),
            'canModerate' => $this->tenantManager($request, $tenant->id),
        ]);
    }

    public function tenantStore(Request $request, TenantContext $context, Event $event)
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $event->tenant_id === (int) $tenant->id && $event->status !== 'cancelled', 404);
        $data = $request->validate(['body' => ['required','string','max:5000']]);
        CommunityPost::create(['tenant_id'=>$tenant->id,'community_group_id'=>null,'event_id'=>$event->id,'user_id'=>$request->user()->id,'body'=>trim($data['body']),'status'=>'active']);
        return back()->with('status','Posted to the event discussion.');
    }

    public function network(Request $request, TenantContext $context, Event $event)
    {
        $this->assertNetworkEvent($context, $event);
        $blocked = $this->blockedIds((int) $request->user()->id);
        return view('member.community.event-discussion', [
            'scope' => 'network',
            'tenant' => null,
            'event' => $event->loadMissing('tenant'),
            'posts' => NetworkCommunityPost::query()
                ->where('event_id',$event->id)->whereNull('network_community_group_id')->where('status','active')
                ->when($blocked !== [], fn ($q) => $q->whereNotIn('user_id',$blocked))
                ->with(['user:id,name,display_name','comments' => fn ($q) => $q->where('status','active')->when($blocked !== [], fn ($c) => $c->whereNotIn('user_id',$blocked))->oldest()->with('user:id,name,display_name'),'reactions'])
                ->orderByDesc('is_pinned')->latest()->paginate(25),
            'canModerate' => (bool) $request->user()->is_platform_admin,
        ]);
    }

    public function networkStore(Request $request, TenantContext $context, Event $event)
    {
        $this->assertNetworkEvent($context, $event);
        $data = $request->validate(['body' => ['required','string','max:5000']]);
        NetworkCommunityPost::create(['network_community_group_id'=>null,'event_id'=>$event->id,'user_id'=>$request->user()->id,'body'=>trim($data['body']),'status'=>'active']);
        return back()->with('status','Posted to the event discussion.');
    }

    private function assertNetworkEvent(TenantContext $context, Event $event): void
    {
        abort_unless(Edition::isHosted() && ! $context->check(), 404);
        $event->loadMissing('tenant');
        abort_unless($event->status === 'published' && $event->visibility === 'public' && $event->tenant?->isNetworkListed(), 404);
    }

    private function blockedIds(int $userId): array
    {
        return DB::table('user_blocks')->where('user_id',$userId)->pluck('blocked_user_id')
            ->merge(DB::table('user_blocks')->where('blocked_user_id',$userId)->pluck('user_id'))
            ->map(fn ($id)=>(int)$id)->unique()->values()->all();
    }

    private function tenantManager(Request $request, int $tenantId): bool
    {
        $pivot = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;
        return (bool) ($pivot && $pivot->status === 'active' && in_array($pivot->role,['owner','admin','manager'],true));
    }
}
