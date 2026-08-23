<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use App\Services\ProductCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ProductInsightsController extends Controller
{
    public function analytics(ProductCompletionService $products): View
    {
        $gross=(int)DB::table('marketplace_ledger_entries')->where('status','posted')->sum('gross_cents');
        $fees=(int)DB::table('marketplace_ledger_entries')->where('status','posted')->sum('platform_fee_cents');
        return view('admin.product-insights',[
            'section'=>'analytics',
            'metrics'=>[
                'users'=>User::count(),'active_users'=>User::where('status','active')->count(),'clubs'=>Tenant::where('status','active')->count(),
                'events'=>Event::where('status','published')->count(),'upcoming_events'=>Event::where('status','published')->where('starts_at','>=',now())->count(),
                'gross_cents'=>$gross,'fee_cents'=>$fees,'connections'=>DB::table('user_connections')->where('status','accepted')->count(),
                'reviews'=>DB::table('reviews')->where('status','published')->count(),'campaigns'=>DB::table('marketing_campaigns')->count(),
                'referrals'=>DB::table('referral_attributions')->count(),
            ],
            'markets'=>Event::where('status','published')->whereNotNull('city')->select('city',DB::raw('count(*) as total'))->groupBy('city')->orderByDesc('total')->limit(15)->get(),
            'readiness'=>$products->readiness(),
        ]);
    }

    public function trust(): View
    {
        return view('admin.product-insights',[
            'section'=>'trust',
            'cases'=>DB::table('trust_cases')->leftJoin('users as subject','subject.id','=','trust_cases.subject_user_id')
                ->select('trust_cases.*','subject.display_name as subject_name')
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
                ->latest('trust_cases.created_at')->paginate(60),
            'blocks'=>DB::table('user_blocks')->count(),
            'openReports'=>DB::table('reports')->whereIn('status',['open','reviewing'])->count(),
            'pendingVerifications'=>Verification::with(['user'=>fn($q)=>$q->select('id','name','display_name','email')])
                ->whereIn('status',['pending','reviewing','failed'])->latest()->limit(50)->get(),
            'unlinkedReports'=>DB::table('reports')->leftJoin('trust_cases','trust_cases.report_id','=','reports.id')
                ->whereIn('reports.status',['open','reviewing'])->whereNull('trust_cases.id')
                ->select('reports.*')->latest('reports.created_at')->limit(50)->get(),
        ]);
    }

    public function updateTrust(Request $request,int $id): RedirectResponse
    {
        $data=$request->validate(['status'=>['required','in:open,reviewing,resolved,dismissed'],'priority'=>['required','in:low,normal,high,urgent'],'resolution'=>['nullable','string','max:5000']]);
        $data['resolved_at']=in_array($data['status'],['resolved','dismissed'],true)?now():null;
        $data['assigned_to']=$request->user()->id;
        $data['updated_at']=now();
        abort_unless(DB::table('trust_cases')->where('id',$id)->update($data),404);
        return back()->with('status','Trust & safety case updated.');
    }

    public function escalateReport(Request $request,int $id): RedirectResponse
    {
        $report=DB::table('reports')->where('id',$id)->first(); abort_unless($report,404);
        $priority=$request->validate(['priority'=>['nullable','in:low,normal,high,urgent']])['priority']??'normal';
        $subjectUserId = in_array((string)$report->reportable_type,['user','App\\Models\\User'],true) ? (int)$report->reportable_id : null;
        DB::table('trust_cases')->updateOrInsert(
            ['report_id'=>$report->id],
            ['reporter_id'=>$report->reporter_id,'subject_user_id'=>$subjectUserId,'tenant_id'=>$report->tenant_id,'type'=>'moderation_report','status'=>'reviewing','priority'=>$priority,'assigned_to'=>$request->user()->id,'notes'=>'Escalated from moderation category: '.$report->category,'updated_at'=>now(),'created_at'=>now()]
        );
        DB::table('reports')->where('id',$report->id)->update(['status'=>'reviewing','assigned_to'=>$request->user()->id,'updated_at'=>now()]);
        return back()->with('status','Moderation report escalated into Trust & Safety.');
    }

    public function escalateVerification(Request $request,Verification $verification): RedirectResponse
    {
        $priority=$request->validate(['priority'=>['nullable','in:low,normal,high,urgent']])['priority']??($verification->status==='failed'?'high':'normal');
        $existing=DB::table('trust_cases')->where('type','verification_review')->where('subject_user_id',$verification->user_id)->where('tenant_id',$verification->tenant_id)->whereIn('status',['open','reviewing'])->first();
        if(!$existing){
            DB::table('trust_cases')->insert([
                'reporter_id'=>null,'subject_user_id'=>$verification->user_id,'tenant_id'=>$verification->tenant_id,'report_id'=>null,
                'type'=>'verification_review','status'=>'reviewing','priority'=>$priority,'assigned_to'=>$request->user()->id,
                // Deliberately store only operational identifiers/status. Raw ID documents or provider payloads never enter trust-case notes.
                'notes'=>'Verification #'.$verification->id.' · '.$verification->type.' · status '.$verification->status.' · provider '.($verification->provider?:'manual'),
                'resolution'=>null,'resolved_at'=>null,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        if($verification->status==='pending') $verification->update(['status'=>'reviewing','reviewed_by'=>$request->user()->id]);
        return back()->with('status','Verification escalated without copying sensitive verification payloads.');
    }
}
