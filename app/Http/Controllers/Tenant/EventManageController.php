<?php
namespace App\Http\Controllers\Tenant;
use App\Models\Event;use App\Models\EventInvitation;use App\Models\EventRsvp;use App\Tenancy\TenantContext;use App\Support\Audit;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;
class EventManageController{
 public function index(TenantContext $c){$t=$c->requireTenant();return view('tenant.manage.events.index',['tenant'=>$t,'events'=>$t->events()->whereNull('parent_event_id')->latest('starts_at')->paginate(25)]);}
 public function create(){return view('tenant.manage.events.edit',['event'=>new Event,'questions'=>collect()]);}
 public function store(Request $r,TenantContext $c){$d=$this->data($r);$d['slug']=$this->uniqueSlug($c->id(),$d['slug']?:$d['title']);$e=$c->requireTenant()->events()->create($d);$this->createRecurrences($e);Audit::write('event.created',$e,after:$e->toArray(),tenantId:$c->id(),request:$r);return redirect()->route('tenant.events.edit',$e)->with('status','Event created.');}
 public function edit(TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);return view('tenant.manage.events.edit',['event'=>$event->load('ticketTypes'),'questions'=>DB::table('event_questions')->where('event_id',$event->id)->orderBy('sort_order')->get(),'invitations'=>EventInvitation::where('event_id',$event->id)->latest()->limit(100)->get()]);}
 public function update(Request $r,TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);$before=$event->toArray();$d=$this->data($r);$d['slug']=$this->uniqueSlug($c->id(),$d['slug']?:$d['title'],$event->id);$event->update($d);Audit::write('event.updated',$event,$before,$event->fresh()->toArray(),$c->id(),$r);return back()->with('status','Event updated. Existing recurrence instances are not silently rewritten.');}
 public function duplicate(TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);$copy=$event->replicate(['parent_event_id']);$copy->title=$event->title.' Copy';$copy->slug=$this->uniqueSlug($c->id(),$event->slug.'-copy');$copy->status='draft';$copy->recurrence_rule=null;$copy->recurrence_until=null;$copy->save();foreach($event->ticketTypes as $type)$copy->ticketTypes()->create($type->only(['name','description','profile_eligibility','membership_required','approval_required','price_cents','currency','quantity','max_per_order','sales_start_at','sales_end_at','active']));return redirect()->route('tenant.events.edit',$copy)->with('status','Event duplicated as a draft.');}
 public function destroy(TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);abort_if($event->orders()->exists(),422,'Events with orders are retained for financial/audit integrity. Cancel the event instead.');$event->delete();return redirect()->route('tenant.events.index')->with('status','Event deleted.');}
 public function attendees(TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);return view('tenant.manage.events.attendees',['event'=>$event,'rsvps'=>$event->rsvps()->with('user')->latest()->paginate(100)]);}
 public function rsvpStatus(Request $r,TenantContext $c,Event $event,$rsvp){
  abort_unless($event->tenant_id===$c->id(),404);
  $d=$r->validate(['status'=>'required|in:pending,approved,rejected,cancelled']);
  DB::transaction(function()use($event,$rsvp,$d){
   $lockedEvent=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   $rv=EventRsvp::where('event_id',$lockedEvent->id)->whereKey($rsvp)->lockForUpdate()->firstOrFail();
   if($d['status']==='approved'&&$lockedEvent->capacity!==null){
    $approved=(int)EventRsvp::where('event_id',$lockedEvent->id)->where('status','approved')->whereKeyNot($rv->id)->sum('guest_count');
    abort_if($approved+(int)$rv->guest_count>(int)$lockedEvent->capacity,422,'This approval would exceed the event capacity.');
   }
   $rv->update(['status'=>$d['status'],'approved_at'=>$d['status']==='approved'?now():null]);
   if($d['status']==='approved')DB::table('event_waitlist')->where('event_id',$lockedEvent->id)->where('user_id',$rv->user_id)->delete();
  });
  return back()->with('status','RSVP updated.');
 }
 public function addQuestion(Request $r,TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);$d=$r->validate(['label'=>'required|string|max:255','type'=>'required|in:text,textarea,select,checkbox','required'=>'nullable|boolean']);DB::table('event_questions')->insert(['event_id'=>$event->id,'label'=>$d['label'],'type'=>$d['type'],'options'=>null,'required'=>$r->boolean('required'),'sort_order'=>(int)DB::table('event_questions')->where('event_id',$event->id)->max('sort_order')+10,'created_at'=>now(),'updated_at'=>now()]);return back()->with('status','RSVP question added.');}
 public function deleteQuestion(TenantContext $c,Event $event,int $question){abort_unless($event->tenant_id===$c->id(),404);DB::table('event_questions')->where('event_id',$event->id)->where('id',$question)->delete();return back()->with('status','RSVP question removed.');}
 public function invite(Request $r,TenantContext $c,Event $event){
  abort_unless($event->tenant_id===$c->id(),404);
  $d=$r->validate(['email'=>'nullable|email|max:255','max_guests'=>'required|integer|min:1|max:10','expires_at'=>'nullable|date|after:now']);
  $rawToken=bin2hex(random_bytes(32));
  $invite=EventInvitation::create([
   'event_id'=>$event->id,
   'created_by'=>$r->user()->id,
   'email'=>isset($d['email'])&&trim((string)$d['email'])!==''?strtolower(trim((string)$d['email'])):null,
   'token'=>'sha256:'.hash('sha256',$rawToken),
   'status'=>'pending',
   'max_guests'=>$d['max_guests'],
   'expires_at'=>$d['expires_at']??null,
  ]);
  Audit::write('event.invitation.created',$invite,after:$invite->only(['event_id','email','status','max_guests','expires_at']),tenantId:$c->id(),request:$r);
  return back()
   ->with('status','Invitation created. Copy the secure invitation link now; for security it will not be shown again.')
   ->with('event_invitation_url',route('event-invitations.show',$rawToken));
 }
 public function revokeInvite(Request $r,TenantContext $c,Event $event,EventInvitation $invitation){abort_unless($event->tenant_id===$c->id()&&$invitation->event_id===$event->id,404);abort_if($invitation->status==='accepted',422,'Accepted invitations are retained for audit history.');$invitation->update(['status'=>'revoked']);Audit::write('event.invitation.revoked',$invitation,tenantId:$c->id(),request:$r);return back()->with('status','Invitation revoked.');}
 private function data(Request $r):array{return $r->validate(['title'=>'required|string|max:180','slug'=>'nullable|string|max:180','summary'=>'nullable|string|max:1000','description'=>'nullable|string|max:30000','category'=>'nullable|string|max:80','visibility'=>'required|in:public,members,unlisted,invite_only,private','rsvp_mode'=>'required|in:instant,approval,application,invite_only','status'=>'required|in:draft,published,cancelled','starts_at'=>'required|date','ends_at'=>'nullable|date|after:starts_at','timezone'=>'required|string|max:80','capacity'=>'nullable|integer|min:1|max:100000','city'=>'nullable|string|max:120','region'=>'nullable|string|max:120','public_location_label'=>'nullable|string|max:190','exact_address'=>'nullable|string|max:500','exact_address_visibility'=>'required|in:approved_attendees,organizer_only,public','dress_code'=>'nullable|string|max:500','rules'=>'nullable|string|max:10000','waitlist_enabled'=>'nullable|boolean','requires_verified_profile'=>'nullable|boolean','registration_opens_at'=>'nullable|date','registration_closes_at'=>'nullable|date|after:registration_opens_at','recurrence_rule'=>'nullable|in:weekly,monthly','recurrence_until'=>'nullable|date|after:starts_at'])+['waitlist_enabled'=>$r->boolean('waitlist_enabled'),'requires_verified_profile'=>$r->boolean('requires_verified_profile')];}
 private function uniqueSlug(int $tenantId,string $value,?int $ignore=null):string{$base=Str::slug($value)?:'event';$slug=$base;$i=2;while(Event::where('tenant_id',$tenantId)->where('slug',$slug)->when($ignore,fn($q)=>$q->whereKeyNot($ignore))->exists())$slug=$base.'-'.$i++;return $slug;}
 private function createRecurrences(Event $parent):void{if(!$parent->recurrence_rule||!$parent->recurrence_until)return;$start=$parent->starts_at->copy();$end=$parent->ends_at?->copy();for($i=1;$i<=52;$i++){$next=$parent->recurrence_rule==='weekly'?$start->copy()->addWeeks($i):$start->copy()->addMonthsNoOverflow($i);if($next->gt($parent->recurrence_until))break;$copy=$parent->replicate();$copy->parent_event_id=$parent->id;$copy->starts_at=$next;$copy->ends_at=$end?($parent->recurrence_rule==='weekly'?$end->copy()->addWeeks($i):$end->copy()->addMonthsNoOverflow($i)):null;$copy->recurrence_rule=null;$copy->recurrence_until=null;$copy->slug=$this->uniqueSlug($parent->tenant_id,$parent->slug.'-'.$next->format('Ymd'));$copy->save();}}
}
