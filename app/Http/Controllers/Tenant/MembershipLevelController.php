<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantMembershipLevel;
use App\Models\TenantMembershipTerm;
use App\Models\User;
use App\Services\MembershipLevelService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MembershipLevelController extends Controller
{
    public function index(Request $request, TenantContext $context, MembershipLevelService $service): View
    {
        $tenant=$context->requireTenant();$service->ensureDefaultLevel($tenant);
        $levels=TenantMembershipLevel::where('tenant_id',$tenant->id)->withCount('terms')->orderBy('sort_order')->orderBy('name')->get();
        $terms=TenantMembershipTerm::query()->where('tenant_id',$tenant->id)->with(['user.profile','level'])->latest('updated_at');
        if($search=trim((string)$request->query('q')))$terms->whereHas('user',fn($user)=>$user->where('email','like','%'.$search.'%')->orWhere('display_name','like','%'.$search.'%')->orWhere('name','like','%'.$search.'%'));
        return view('tenant.manage.membership-levels.index',['tenant'=>$tenant,'levels'=>$levels,'terms'=>$terms->paginate(50)->withQueryString()]);
    }

    public function store(Request $request,TenantContext $context):RedirectResponse
    {
        $tenant=$context->requireTenant();$data=$this->levelData($request);
        if($data['billing_interval']===TenantMembershipLevel::INTERVAL_CUSTOM&&empty($data['duration_days']))throw ValidationException::withMessages(['duration_days'=>'Custom membership terms require a duration in days.']);
        $slug=Str::slug($data['slug']?:$data['name']);if(TenantMembershipLevel::where('tenant_id',$tenant->id)->where('slug',$slug)->exists())throw ValidationException::withMessages(['slug'=>'That membership level slug is already in use.']);
        $price=(int)round(((float)$data['price'])*100);
        $level=TenantMembershipLevel::create(['tenant_id'=>$tenant->id,'name'=>trim($data['name']),'slug'=>$slug,'description'=>$data['description']??null,'price_cents'=>$price,'currency'=>'USD','billing_interval'=>$data['billing_interval'],'duration_days'=>$data['duration_days']??null,'profile_eligibility'=>$data['profile_eligibility'],'guest_limit'=>$data['guest_limit'],'event_discount_percent'=>$data['event_discount_percent'],'requires_approval'=>$request->boolean('requires_approval'),'is_default'=>false,'is_active'=>true,'sort_order'=>(int)($data['sort_order']??100),'benefits'=>array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)($data['benefits']??''))?:[])))]);
        Audit::write('tenant.membership.level.created',$level,after:$level->toArray(),tenantId:$tenant->id,request:$request);return back()->with('status','Membership level created.');
    }

    public function update(Request $request,TenantContext $context,TenantMembershipLevel $level):RedirectResponse{$tenant=$context->requireTenant();$this->requireLevel($level,$tenant->id);$data=$request->validate(['is_active'=>['required','boolean']]);if($level->is_default&&!$data['is_active'])throw ValidationException::withMessages(['is_active'=>'Choose another default membership level before deactivating this one.']);$before=$level->toArray();$level->update(['is_active'=>(bool)$data['is_active']]);Audit::write('tenant.membership.level.status',$level,before:$before,after:$level->fresh()->toArray(),tenantId:$tenant->id,request:$request);return back()->with('status','Membership level status updated.');}
    public function makeDefault(Request $request,TenantContext $context,TenantMembershipLevel $level):RedirectResponse{$tenant=$context->requireTenant();$this->requireLevel($level,$tenant->id);abort_unless($level->is_active,422,'An inactive level cannot be the default.');TenantMembershipLevel::where('tenant_id',$tenant->id)->update(['is_default'=>false]);$level->update(['is_default'=>true]);Audit::write('tenant.membership.level.default',$level,after:['is_default'=>true],tenantId:$tenant->id,request:$request);return back()->with('status',$level->name.' is now the default approved membership.');}
    public function assign(Request $request,TenantContext $context,MembershipLevelService $service):RedirectResponse{$tenant=$context->requireTenant();$data=$request->validate(['email'=>['required','email'],'membership_level_id'=>['required','integer','exists:tenant_membership_levels,id'],'auto_renew'=>['nullable','boolean'],'notes'=>['nullable','string','max:2000']]);$level=TenantMembershipLevel::findOrFail($data['membership_level_id']);$this->requireLevel($level,$tenant->id);$user=User::where('email',$data['email'])->firstOrFail();$term=$service->applyLevel($tenant,$user,$level,$request->user(),'staff',$request->boolean('auto_renew'),$data['notes']??null);return back()->with('status',$level->name.' assigned to '.($user->display_name?:$user->email).'.'.($term->expires_at?' Current term ends '.$term->expires_at->format('M j, Y').'.':' No expiration date.'));}
    public function renew(Request $request,TenantContext $context,TenantMembershipTerm $term,MembershipLevelService $service):RedirectResponse{$tenant=$context->requireTenant();$this->requireTerm($term,$tenant->id);$term=$service->renew($term,$request->user());return back()->with('status',($term->user->display_name?:$term->user->email).' renewed on '.$term->level->name.'.');}
    public function cancel(Request $request,TenantContext $context,TenantMembershipTerm $term,MembershipLevelService $service):RedirectResponse{$tenant=$context->requireTenant();$this->requireTerm($term,$tenant->id);$data=$request->validate(['when'=>['required','in:now,period_end'],'reason'=>['nullable','string','max:2000']]);$service->cancel($term,$request->user(),$data['when']==='period_end',$data['reason']??null);return back()->with('status',$data['when']==='period_end'?'Membership will end at the current period boundary.':'Membership cancelled now.');}
    private function levelData(Request $request):array{return $request->validate(['name'=>['required','string','max:120'],'slug'=>['nullable','string','max:120'],'description'=>['nullable','string','max:2000'],'price'=>['required','numeric','min:0','max:100000'],'billing_interval'=>['required','in:none,monthly,quarterly,annual,lifetime,custom'],'duration_days'=>['nullable','integer','min:1','max:3650'],'profile_eligibility'=>['required','in:any,couple,individual'],'guest_limit'=>['required','integer','min:0','max:100'],'event_discount_percent'=>['required','integer','min:0','max:100'],'requires_approval'=>['nullable','boolean'],'sort_order'=>['nullable','integer','min:0','max:10000'],'benefits'=>['nullable','string','max:5000']]);}
    private function requireLevel(TenantMembershipLevel $level,int $tenantId):void{abort_unless((int)$level->tenant_id===$tenantId,404);}private function requireTerm(TenantMembershipTerm $term,int $tenantId):void{abort_unless((int)$term->tenant_id===$tenantId,404);}
}
