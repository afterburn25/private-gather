<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventRsvp;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BadgeService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckinController extends Controller
{
    public function index(Request $request,TenantContext $ctx,Event $event,BadgeService $badges)
    {
        $tenant=$ctx->requireTenant(); abort_unless($event->tenant_id===$tenant->id,404); $search=trim((string)$request->query('q'));
        $attendees=EventRsvp::query()->with('user.profile')->where('event_id',$event->id)->where('status','approved')->when($search!=='',function($query)use($search):void{$query->whereHas('user',fn($user)=>$user->where('name','like','%'.$search.'%')->orWhere('display_name','like','%'.$search.'%')->orWhere('email','like','%'.$search.'%'));})->orderBy('id')->paginate(40)->withQueryString();
        $doorBadges=[];$activeMembership=[];foreach($attendees as $rsvp){if(!$rsvp->user)continue;$doorBadges[$rsvp->user_id]=$badges->forDoor($rsvp->user,$tenant->id);$activeMembership[$rsvp->user_id]=$rsvp->user->tenants()->where('tenants.id',$tenant->id)->wherePivot('status','active')->exists();}
        $checkedByUser=EventCheckin::query()->where('event_id',$event->id)->whereNotNull('user_id')->selectRaw('user_id, SUM(guest_count) AS checked_count')->groupBy('user_id')->pluck('checked_count','user_id');
        $checkins=EventCheckin::query()->with(['user.profile','ticket'])->where('event_id',$event->id)->latest('checked_in_at')->paginate(50,['*'],'checkins');
        $checkedIn=(int)EventCheckin::where('event_id',$event->id)->sum('guest_count');$approved=(int)EventRsvp::where('event_id',$event->id)->where('status','approved')->sum('guest_count');$remaining=$event->capacity===null?null:max(0,$event->capacity-$checkedIn);
        return view('tenant.manage.checkin.index',compact('event','tenant','attendees','checkins','doorBadges','activeMembership','checkedByUser','checkedIn','approved','remaining','search'));
    }

    public function manual(Request $request,TenantContext $ctx,Event $event,BadgeService $badges)
    {
        $tenant=$ctx->requireTenant();abort_unless($event->tenant_id===$tenant->id,404);abort_unless($event->status==='published',422,'Only published events can accept check-ins.');
        $data=$request->validate(['user_id'=>'nullable|integer|exists:users,id','guest_name'=>'nullable|string|max:120','guest_count'=>'required|integer|min:1|max:20']);if(!isset($data['user_id'])&&blank($data['guest_name']??null))throw ValidationException::withMessages(['guest_name'=>'Enter a walk-in name or choose an approved attendee.']);
        $userId=DB::transaction(function()use($data,$event,$request):?int{$lockedEvent=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();abort_unless($lockedEvent->status==='published',422,'Only published events can accept check-ins.');$guestCount=(int)$data['guest_count'];$alreadyInside=(int)EventCheckin::where('event_id',$lockedEvent->id)->sum('guest_count');if($lockedEvent->capacity!==null)abort_if($alreadyInside+$guestCount>$lockedEvent->capacity,422,'This check-in would exceed event capacity.');$userId=isset($data['user_id'])?(int)$data['user_id']:null;if($userId!==null){$rsvpGuests=(int)(EventRsvp::where('event_id',$lockedEvent->id)->where('user_id',$userId)->where('status','approved')->value('guest_count')??0);$ticketGuests=Ticket::where('user_id',$userId)->whereIn('status',['valid','used'])->whereHas('orderItem.order',fn($q)=>$q->where('event_id',$lockedEvent->id))->count();$entitlement=max($rsvpGuests,$ticketGuests);abort_if($entitlement<1,422,'This member is not an approved attendee or ticket holder for this event.');$alreadyCheckedIn=(int)EventCheckin::where('event_id',$lockedEvent->id)->where('user_id',$userId)->sum('guest_count');abort_if($alreadyCheckedIn+$guestCount>$entitlement,422,'This check-in would exceed the member’s approved guest/ticket allowance.');}EventCheckin::create(['event_id'=>$lockedEvent->id,'user_id'=>$userId,'checked_in_by'=>$request->user()->id,'method'=>$userId?'manual':'walk_in','guest_count'=>$guestCount,'checked_in_at'=>now(),'metadata'=>$userId?null:['guest_name'=>trim((string)$data['guest_name'])]]);return $userId;});
        if($userId!==null&&($user=User::find($userId)))$badges->syncForUser($user,$tenant->id);return back()->with('status',$userId?'Approved attendee checked in.':'Walk-in guest checked in.');
    }

    public function ticket(Request $request,TenantContext $ctx,Event $event,BadgeService $badges)
    {
        $tenant=$ctx->requireTenant();abort_unless($event->tenant_id===$tenant->id,404);abort_unless($event->status==='published',422,'Only published events can accept check-ins.');$data=$request->validate(['qr_token'=>'required|string|max:100']);
        $userId=DB::transaction(function()use($data,$event,$request):?int{$lockedEvent=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();abort_unless($lockedEvent->status==='published',422,'Only published events can accept check-ins.');$alreadyInside=(int)EventCheckin::where('event_id',$lockedEvent->id)->sum('guest_count');if($lockedEvent->capacity!==null)abort_if($alreadyInside+1>$lockedEvent->capacity,422,'This check-in would exceed event capacity.');$ticket=Ticket::where('qr_token',$data['qr_token'])->lockForUpdate()->firstOrFail();$ticket->load('orderItem.order');abort_unless(optional(optional($ticket->orderItem)->order)->event_id===$lockedEvent->id,422,'Ticket is for another event.');abort_if($ticket->status!=='valid'||$ticket->checked_in_at,422,'Ticket is not valid for check-in.');$ticket->update(['checked_in_at'=>now(),'status'=>'used']);EventCheckin::create(['event_id'=>$lockedEvent->id,'user_id'=>$ticket->user_id,'ticket_id'=>$ticket->id,'checked_in_by'=>$request->user()->id,'method'=>'qr','guest_count'=>1,'checked_in_at'=>now()]);return $ticket->user_id;});
        if($userId!==null&&($user=User::find($userId)))$badges->syncForUser($user,$tenant->id);return back()->with('status','Ticket accepted.');
    }
}
