<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MemberBadge;
use App\Models\ClubReward;
use App\Models\MemberPointLedger;
use App\Models\RewardRedemption;
use App\Models\MembershipApplication;
use App\Models\Report;
use App\Models\User;
use App\Services\CommunityNotifier;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommunityMemberController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant=$context->requireTenant();
        $applications=MembershipApplication::query()->where('tenant_id',$tenant->id)->with('user.profile')->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->latest()->paginate(50,['*'],'applications');
        $members=$tenant->users()->wherePivot('role','member')->with(['profile','badges'=>fn($q)=>$q->where('tenant_id',$tenant->id)])->orderByRaw('COALESCE(display_name,name)')->paginate(100,['users.*'],'members');
        $reports=Report::query()->where('tenant_id',$tenant->id)->whereIn('status',['open','reviewing'])->with('reporter:id,name,display_name')->latest()->limit(50)->get();
        $rewards=ClubReward::query()->where('tenant_id',$tenant->id)->orderBy('points_cost')->get();
        $redemptions=RewardRedemption::query()->where('tenant_id',$tenant->id)->where('status','pending')->with(['reward','user:id,name,display_name'])->latest()->limit(50)->get();
        return view('tenant.manage.community-members',compact('tenant','applications','members','reports','rewards','redemptions'));
    }

    public function review(Request $request, TenantContext $context, MembershipApplication $application, CommunityNotifier $notifier): RedirectResponse
    {
        $tenant=$context->requireTenant();
        abort_unless((int)$application->tenant_id===(int)$tenant->id,404);
        $data=$request->validate(['decision'=>'required|in:approve,decline','review_note'=>'nullable|string|max:3000']);
        $approved=$data['decision']==='approve';
        DB::transaction(function()use($application,$request,$tenant,$data,$approved):void{
            $application->update(['status'=>$approved?'approved':'declined','review_note'=>$data['review_note']??null,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);
            $tenant->users()->syncWithoutDetaching([$application->user_id=>['role'=>'member','status'=>$approved?'active':'declined']]);
        });
        $notifier->notify((int)$application->user_id,(int)$tenant->id,'membership',['title'=>$approved?'Your '.$tenant->name.' membership was approved':'Your '.$tenant->name.' membership application was reviewed','url'=>route('dashboard')]);
        Audit::write('community.membership.review',$application,tenantId:$tenant->id,request:$request);
        return back()->with('status',$approved?'Membership approved.':'Application declined.');
    }

    public function status(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant=$context->requireTenant();
        $pivot=$tenant->users()->whereKey($user->id)->wherePivot('role','member')->first()?->pivot;abort_unless($pivot,404);
        $data=$request->validate(['status'=>'required|in:active,pending,suspended,banned']);
        $tenant->users()->updateExistingPivot($user->id,['status'=>$data['status']]);
        Audit::write('community.member.status',$user,before:['status'=>$pivot->status],after:['status'=>$data['status']],tenantId:$tenant->id,request:$request);
        return back()->with('status','Club membership status updated.');
    }

    public function badge(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant=$context->requireTenant();abort_unless($tenant->users()->whereKey($user->id)->exists(),404);
        $data=$request->validate(['code'=>'required|string|max:80','label'=>'required|string|max:120','icon'=>'nullable|string|max:40','visibility'=>'required|in:members,connections,private','expires_at'=>'nullable|date|after:today']);
        MemberBadge::updateOrCreate(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'code'=>$data['code']],['label'=>$data['label'],'icon'=>$data['icon']??null,'visibility'=>$data['visibility'],'awarded_by'=>$request->user()->id,'awarded_at'=>now(),'expires_at'=>$data['expires_at']??null]);
        return back()->with('status','Member badge saved.');
    }

    public function report(Request $request, TenantContext $context, Report $report): RedirectResponse
    {
        $tenant=$context->requireTenant();abort_unless((int)$report->tenant_id===(int)$tenant->id,404);
        $data=$request->validate(['status'=>'required|in:open,reviewing,resolved,dismissed']);
        $report->update(['status'=>$data['status'],'assigned_to'=>$request->user()->id]);
        return back()->with('status','Community report updated.');
    }

    public function verifyMemberCard(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate(['member_code' => 'required|string|max:80']);
        $code = strtoupper(trim($data['member_code']));
        if (! preg_match('/^PG-(\d+)-(\d+)-([A-F0-9]{8})$/', $code, $match)) {
            return back()->withErrors(['member_code' => 'That membership code is not valid.']);
        }

        $tenantId = (int) $match[1];
        $userId = (int) $match[2];
        abort_unless($tenantId === (int) $tenant->id, 404);
        $expected = strtoupper(substr(hash_hmac('sha256', $tenantId.':'.$userId, (string) config('app.key')), 0, 8));
        if (! hash_equals($expected, $match[3])) {
            return back()->withErrors(['member_code' => 'That membership code failed verification.']);
        }

        $member = $tenant->users()->whereKey($userId)->first();
        abort_unless($member, 404);
        return back()->with('member_card_verification', [
            'name' => $member->display_name ?: $member->name,
            'status' => (string) $member->pivot->status,
            'role' => (string) $member->pivot->role,
            'user_id' => (int) $member->id,
        ])->with('status', 'Membership card verified.');
    }

    public function awardPoints(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant=$context->requireTenant();abort_unless($tenant->users()->whereKey($user->id)->exists(),404);
        $data=$request->validate(['points'=>'required|integer|min:-100000|max:100000|not_in:0','reason'=>'required|string|max:190']);
        MemberPointLedger::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'points'=>(int)$data['points'],'reason'=>$data['reason'],'source_type'=>'staff_adjustment','issued_by'=>$request->user()->id]);
        return back()->with('status','Member points updated.');
    }

    public function createReward(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant=$context->requireTenant();$data=$request->validate(['name'=>'required|string|max:160','description'=>'nullable|string|max:2000','points_cost'=>'required|integer|min:1|max:1000000','inventory'=>'nullable|integer|min:0|max:1000000']);
        ClubReward::create(['tenant_id'=>$tenant->id,'name'=>$data['name'],'description'=>$data['description']??null,'points_cost'=>(int)$data['points_cost'],'inventory'=>$data['inventory']??null,'active'=>true]);
        return back()->with('status','Club reward created.');
    }

    public function redemption(Request $request, TenantContext $context, RewardRedemption $redemption): RedirectResponse
    {
        $tenant=$context->requireTenant();abort_unless((int)$redemption->tenant_id===(int)$tenant->id,404);$data=$request->validate(['status'=>'required|in:fulfilled,cancelled']);
        if($data['status']==='cancelled'&&$redemption->status==='pending'){MemberPointLedger::create(['tenant_id'=>$tenant->id,'user_id'=>$redemption->user_id,'points'=>$redemption->points_spent,'reason'=>'Cancelled reward redemption refund','source_type'=>'reward_redemption','source_id'=>$redemption->id,'issued_by'=>$request->user()->id]);if($redemption->reward?->inventory!==null)$redemption->reward()->increment('inventory');}
        $redemption->update(['status'=>$data['status'],'fulfilled_by'=>$request->user()->id,'fulfilled_at'=>$data['status']==='fulfilled'?now():null]);return back()->with('status','Reward redemption updated.');
    }

    public function settings(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant=$context->requireTenant();
        $data=$request->validate([
            'membership_registration'=>'required|in:approval,open',
            'community_wall'=>'nullable|boolean',
            'member_video_uploads'=>'nullable|boolean',
            'member_directory'=>'nullable|boolean',
            'stories_enabled'=>'nullable|boolean',
            'groups_enabled'=>'nullable|boolean',
            'event_community'=>'nullable|boolean',
        ]);
        $settings=$tenant->settings??[];
        $settings['membership_registration']=$data['membership_registration'];
        foreach(['community_wall','member_video_uploads','member_directory','stories_enabled','groups_enabled','event_community'] as $key){$settings[$key]=$request->boolean($key);}
        $tenant->update(['settings'=>$settings]);
        return back()->with('status','Community settings saved.');
    }
}
