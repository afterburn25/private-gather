<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ProductExperienceController extends Controller
{
    public function onboarding(Request $request): View
    {
        $progress=DB::table('onboarding_progress')->where('user_id',$request->user()->id)->first();
        return view('member.product-center',['section'=>'onboarding','progress'=>$progress,'profile'=>$request->user()->profile]);
    }
    public function saveOnboarding(Request $request): RedirectResponse
    {
        $data=$request->validate(['display_name'=>['required','string','max:120'],'profile_type'=>['required','in:individual,couple'],'city'=>['nullable','string','max:120'],'region'=>['nullable','string','max:120'],'headline'=>['nullable','string','max:180'],'interests'=>['nullable','string','max:1000'],'discoverable'=>['nullable','boolean']]);
        $request->user()->update(['display_name'=>trim($data['display_name'])]);
        Profile::updateOrCreate(['user_id'=>$request->user()->id],['profile_type'=>$data['profile_type'],'city'=>trim((string)($data['city']??''))?:null,'region'=>trim((string)($data['region']??''))?:null,'headline'=>trim((string)($data['headline']??''))?:null,'interests'=>collect(explode(',',(string)($data['interests']??'')))->map(fn($x)=>trim($x))->filter()->values()->all(),'discoverable'=>$request->boolean('discoverable')]);
        DB::table('onboarding_progress')->updateOrInsert(['user_id'=>$request->user()->id],['member_step'=>5,'member_completed_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
        return redirect()->route('dashboard')->with('status','Your Private Gather profile is ready.');
    }
    public function saved(Request $request): View
    {
        $favorites=DB::table('favorites')->where('user_id',$request->user()->id)->latest()->get();
        $eventIds=$favorites->where('target_type','event')->pluck('target_id'); $tenantIds=$favorites->where('target_type','tenant')->pluck('target_id');
        return view('member.product-center',['section'=>'saved','events'=>Event::with('tenant')->whereIn('id',$eventIds)->get(),'clubs'=>Tenant::with('primaryDomain')->whereIn('id',$tenantIds)->get(),'searches'=>DB::table('saved_searches')->where('user_id',$request->user()->id)->latest()->get()]);
    }
    public function favorite(Request $request,string $type,int $id): RedirectResponse
    {
        abort_unless(in_array($type,['event','tenant'],true),404); $this->assertTarget($type,$id);
        DB::table('favorites')->updateOrInsert(['user_id'=>$request->user()->id,'target_type'=>$type,'target_id'=>$id],['updated_at'=>now(),'created_at'=>now()]); return back()->with('status','Saved.');
    }
    public function unfavorite(Request $request,string $type,int $id): RedirectResponse
    { DB::table('favorites')->where(['user_id'=>$request->user()->id,'target_type'=>$type,'target_id'=>$id])->delete(); return back()->with('status','Removed from saved items.'); }
    public function saveSearch(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:120'],'search_type'=>['required','in:events,clubs'],'filters'=>['required','array'],'alert_enabled'=>['nullable','boolean']]);
        DB::table('saved_searches')->insert(['user_id'=>$request->user()->id,'name'=>$data['name'],'search_type'=>$data['search_type'],'filters'=>json_encode($data['filters']),'alert_enabled'=>$request->boolean('alert_enabled'),'created_at'=>now(),'updated_at'=>now()]); return back()->with('status','Search saved.');
    }
    public function deleteSearch(Request $request,int $id): RedirectResponse
    { DB::table('saved_searches')->where('id',$id)->where('user_id',$request->user()->id)->delete(); return back()->with('status','Saved search removed.'); }
    public function notifications(Request $request): View
    {
        $prefs=DB::table('notification_preferences')->where('user_id',$request->user()->id)->first();
        return view('member.product-center',['section'=>'notifications','notifications'=>DB::table('platform_notifications')->where('user_id',$request->user()->id)->latest()->paginate(40),'prefs'=>$prefs]);
    }
    public function notificationPreferences(Request $request): RedirectResponse
    {
        $fields=['email_events','email_messages','email_marketing','browser_notifications','email_membership','email_tickets','push_messages','push_events','push_membership','push_tickets']; $values=[]; foreach($fields as $field)$values[$field]=$request->boolean($field);
        $values['digest_frequency']=$request->validate(['digest_frequency'=>['required','in:instant,daily,weekly']])['digest_frequency']; $values['updated_at']=now();
        DB::table('notification_preferences')->updateOrInsert(['user_id'=>$request->user()->id],$values+['created_at'=>now()]); return back()->with('status','Notification preferences updated.');
    }
    public function readNotification(Request $request,string $id): RedirectResponse
    { DB::table('platform_notifications')->where('id',$id)->where('user_id',$request->user()->id)->update(['read_at'=>now(),'updated_at'=>now()]); return back(); }
    public function readAllNotifications(Request $request): RedirectResponse
    { DB::table('platform_notifications')->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]); return back()->with('status','Notifications marked read.'); }
    public function privacy(Request $request): View
    { return view('member.product-center',['section'=>'privacy','privacy'=>DB::table('user_privacy_settings')->where('user_id',$request->user()->id)->first(),'blocks'=>DB::table('user_blocks')->join('users','users.id','=','user_blocks.blocked_id')->where('blocker_id',$request->user()->id)->select('user_blocks.*','users.display_name','users.name')->get()]); }
    public function savePrivacy(Request $request): RedirectResponse
    {
        $data=$request->validate(['profile_visibility'=>['required','in:private,connections,members'],'messages_from'=>['required','in:nobody,connections,members'],'location_visibility'=>['required','in:hidden,city,region'],'memberships_visibility'=>['required','in:private,connections,members'],'attendance_visibility'=>['required','in:private,connections,members'],'online_visibility'=>['required','in:hidden,connections,members']]);
        $data['read_receipts']=$request->boolean('read_receipts'); $data['profile_view_receipts']=$request->boolean('profile_view_receipts'); $data['updated_at']=now();
        DB::table('user_privacy_settings')->updateOrInsert(['user_id'=>$request->user()->id],$data+['created_at'=>now()]); return back()->with('status','Privacy controls updated.');
    }
    public function connections(Request $request): View
    {
        $id=$request->user()->id; $rows=DB::table('user_connections')->where('requester_id',$id)->orWhere('addressee_id',$id)->latest()->get(); $userIds=$rows->flatMap(fn($r)=>[$r->requester_id,$r->addressee_id])->unique()->reject(fn($v)=>(int)$v===$id); $users=User::whereIn('id',$userIds)->with('profile')->get()->keyBy('id');
        return view('member.product-center',['section'=>'connections','connections'=>$rows,'connectionUsers'=>$users,'following'=>DB::table('user_follows')->where('user_id',$id)->get()]);
    }
    public function requestConnection(Request $request,User $user,ProductCompletionService $products): RedirectResponse
    {
        abort_if($user->id===$request->user()->id,422); $this->assertNotBlocked($request->user()->id,$user->id);
        $existing=DB::table('user_connections')->where(fn($q)=>$q->where(['requester_id'=>$request->user()->id,'addressee_id'=>$user->id]))->orWhere(fn($q)=>$q->where(['requester_id'=>$user->id,'addressee_id'=>$request->user()->id]))->first(); abort_if($existing,422,'A connection state already exists.');
        DB::table('user_connections')->insert(['requester_id'=>$request->user()->id,'addressee_id'=>$user->id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]); $products->notify($user->id,'connection.request',['from_user_id'=>$request->user()->id,'label'=>($request->user()->display_name?:$request->user()->name).' sent a connection request.']); return back()->with('status','Connection request sent.');
    }
    public function respondConnection(Request $request,int $id): RedirectResponse
    { $decision=$request->validate(['decision'=>['required','in:accept,decline']])['decision']; $row=DB::table('user_connections')->where('id',$id)->where('addressee_id',$request->user()->id)->where('status','pending')->first(); abort_unless($row,404); DB::table('user_connections')->where('id',$id)->update(['status'=>$decision==='accept'?'accepted':'declined','accepted_at'=>$decision==='accept'?now():null,'updated_at'=>now()]); return back()->with('status',$decision==='accept'?'Connection accepted.':'Request declined.'); }
    public function removeConnection(Request $request,int $id): RedirectResponse
    { DB::table('user_connections')->where('id',$id)->where(fn($q)=>$q->where('requester_id',$request->user()->id)->orWhere('addressee_id',$request->user()->id))->delete(); return back()->with('status','Connection removed.'); }
    public function follow(Request $request,string $type,int $id): RedirectResponse
    { abort_unless(in_array($type,['tenant','user'],true),404); $this->assertTarget($type,$id); DB::table('user_follows')->updateOrInsert(['user_id'=>$request->user()->id,'target_type'=>$type,'target_id'=>$id],['created_at'=>now(),'updated_at'=>now()]); return back()->with('status','Following.'); }
    public function unfollow(Request $request,string $type,int $id): RedirectResponse
    { DB::table('user_follows')->where(['user_id'=>$request->user()->id,'target_type'=>$type,'target_id'=>$id])->delete(); return back()->with('status','Unfollowed.'); }
    public function block(Request $request,User $user): RedirectResponse
    { abort_if($user->id===$request->user()->id,422); DB::transaction(function()use($request,$user){DB::table('user_blocks')->updateOrInsert(['blocker_id'=>$request->user()->id,'blocked_id'=>$user->id],['created_at'=>now(),'updated_at'=>now()]); DB::table('user_connections')->where(fn($q)=>$q->where(['requester_id'=>$request->user()->id,'addressee_id'=>$user->id]))->orWhere(fn($q)=>$q->where(['requester_id'=>$user->id,'addressee_id'=>$request->user()->id]))->delete();}); return back()->with('status','Member blocked.'); }
    public function unblock(Request $request,User $user): RedirectResponse
    { DB::table('user_blocks')->where(['blocker_id'=>$request->user()->id,'blocked_id'=>$user->id])->delete(); return back()->with('status','Member unblocked.'); }
    public function review(Request $request): RedirectResponse
    {
        $data=$request->validate(['tenant_id'=>['required','integer','exists:tenants,id'],'event_id'=>['nullable','integer','exists:events,id'],'rating'=>['required','integer','between:1,5'],'body'=>['nullable','string','max:4000']]); $tenant=Tenant::findOrFail($data['tenant_id']); $event=null; $verified=false;
        if(!empty($data['event_id'])){ $event=Event::whereKey($data['event_id'])->where('tenant_id',$tenant->id)->firstOrFail(); $verified=$event->rsvps()->where('user_id',$request->user()->id)->where('status','approved')->exists() || $event->orders()->where('user_id',$request->user()->id)->whereIn('status',['paid','completed'])->exists(); abort_unless($verified,403,'Only verified attendees may review this event.'); }
        else { abort_unless($request->user()->tenants()->whereKey($tenant->id)->wherePivot('status','active')->exists(),403,'Only active members may review this community.'); }
        $query=DB::table('reviews')->where('user_id',$request->user()->id)->where('tenant_id',$tenant->id); $event?$query->where('event_id',$event->id):$query->whereNull('event_id'); $existing=$query->first(); $values=['rating'=>$data['rating'],'body'=>trim((string)($data['body']??''))?:null,'status'=>'published','verified_attendee'=>$verified,'updated_at'=>now()]; $existing?DB::table('reviews')->where('id',$existing->id)->update($values):DB::table('reviews')->insert($values+['user_id'=>$request->user()->id,'tenant_id'=>$tenant->id,'event_id'=>$event?->id,'created_at'=>now()]); return back()->with('status','Review saved.');
    }
    private function assertTarget(string $type,int $id): void { if($type==='event')abort_unless(Event::whereKey($id)->exists(),404); elseif($type==='tenant')abort_unless(Tenant::whereKey($id)->exists(),404); elseif($type==='user')abort_unless(User::whereKey($id)->exists(),404); else abort(404); }
    private function assertNotBlocked(int $a,int $b): void { abort_if(DB::table('user_blocks')->where(fn($q)=>$q->where(['blocker_id'=>$a,'blocked_id'=>$b]))->orWhere(fn($q)=>$q->where(['blocker_id'=>$b,'blocked_id'=>$a]))->exists(),403,'Connection is not available.'); }
}
