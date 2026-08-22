<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\TenantMembershipLevel;
use App\Models\TenantMembershipTerm;
use App\Services\MembershipLevelService;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function show(Request $request,TenantContext $context,MembershipLevelService $service):View
    {
        $tenant=$context->requireTenant();$term=TenantMembershipTerm::with('level')->where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)->first();if($term)$service->markExpiredIfDue($term);
        $levels=TenantMembershipLevel::where('tenant_id',$tenant->id)->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get();
        return view('member.membership',['tenant'=>$tenant,'term'=>$term?->fresh('level'),'levels'=>$levels,'hasMembership'=>TenantMembership::hasActiveMembership($request->user(),$tenant->id)]);
    }
}
