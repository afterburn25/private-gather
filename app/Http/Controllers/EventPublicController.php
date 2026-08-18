<?php
namespace App\Http\Controllers;
use App\Models\Event;use App\Services\Analytics;use App\Tenancy\TenantContext;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;
class EventPublicController extends Controller{
 public function show(Request $r,TenantContext $c,Event $event,Analytics $analytics){
  abort_unless($event->status==='published',404);$event->load(['tenant.branding','ticketTypes'=>fn($q)=>$q->where('active',true)->orderBy('price_cents')]);
  if($c->check()){abort_unless($event->tenant_id===$c->id(),404);if(in_array($event->visibility,['private','invite_only'],true))abort_unless($r->user()&&$event->rsvps()->where('user_id',$r->user()->id)->where('status','approved')->exists(),403);}
  else abort_unless($event->visibility==='public',404);
  if($event->visibility==='members')abort_unless($r->user(),403);
  $analytics->record('event.view',$r,[],$event->tenant_id,$event->id);
  $questions=DB::table('event_questions')->where('event_id',$event->id)->orderBy('sort_order')->get();
  return view('events.show',['event'=>$event,'remaining'=>$event->remainingCapacity(),'questions'=>$questions]);
 }
}