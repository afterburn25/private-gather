<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Plan;use App\Models\Tenant;use App\Models\TenantSubscription;use Illuminate\Http\Request;
class TenantController extends Controller{
 public function index(Request $r){$q=Tenant::with(['primaryDomain','subscription.plan']);if($s=trim((string)$r->query('q')))$q->where('name','like','%'.$s.'%');return view('admin.tenants.index',['tenants'=>$q->latest()->paginate(100)->withQueryString(),'plans'=>Plan::where('active',true)->orderBy('sort_order')->get()]);}
 public function update(Request $r,Tenant $tenant){$d=$r->validate(['status'=>'required|in:active,suspended,closed','plan_id'=>'nullable|integer|exists:plans,id']);$tenant->update(['status'=>$d['status']]);if(!empty($d['plan_id']))TenantSubscription::updateOrCreate(['tenant_id'=>$tenant->id],['plan_id'=>$d['plan_id'],'status'=>'active']);return back()->with('status','Organization updated.');}
}