<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ProductInsightsController extends Controller
{
    public function analytics(ProductCompletionService $products): View
    {
        $gross=(int)DB::table('marketplace_ledger_entries')->where('status','posted')->sum('gross_cents'); $fees=(int)DB::table('marketplace_ledger_entries')->where('status','posted')->sum('platform_fee_cents');
        return view('admin.product-insights',['section'=>'analytics','metrics'=>['users'=>User::count(),'active_users'=>User::where('status','active')->count(),'clubs'=>Tenant::where('status','active')->count(),'events'=>Event::where('status','published')->count(),'upcoming_events'=>Event::where('status','published')->where('starts_at','>=',now())->count(),'gross_cents'=>$gross,'fee_cents'=>$fees,'connections'=>DB::table('user_connections')->where('status','accepted')->count(),'reviews'=>DB::table('reviews')->where('status','published')->count(),'campaigns'=>DB::table('marketing_campaigns')->count(),'referrals'=>DB::table('referral_attributions')->count()],'markets'=>Event::where('status','published')->whereNotNull('city')->select('city',DB::raw('count(*) as total'))->groupBy('city')->orderByDesc('total')->limit(15)->get(),'readiness'=>$products->readiness()]);
    }
    public function trust(): View { return view('admin.product-insights',['section'=>'trust','cases'=>DB::table('trust_cases')->leftJoin('users as subject','subject.id','=','trust_cases.subject_user_id')->select('trust_cases.*','subject.display_name as subject_name')->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")->latest('trust_cases.created_at')->paginate(60),'blocks'=>DB::table('user_blocks')->count(),'openReports'=>DB::table('reports')->whereIn('status',['open','pending'])->count()]); }
    public function updateTrust(Request $request,int $id): RedirectResponse
    { $data=$request->validate(['status'=>['required','in:open,reviewing,resolved,dismissed'],'priority'=>['required','in:low,normal,high,urgent'],'resolution'=>['nullable','string','max:5000']]); $data['resolved_at']=in_array($data['status'],['resolved','dismissed'],true)?now():null; $data['updated_at']=now(); abort_unless(DB::table('trust_cases')->where('id',$id)->update($data),404); return back()->with('status','Trust & safety case updated.'); }
}
