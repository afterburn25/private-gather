<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Models\AnalyticsEvent;use App\Models\Event;use App\Models\EventCheckin;use App\Models\EventRsvp;use App\Models\Order;use App\Tenancy\TenantContext;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\StreamedResponse;
class AnalyticsController extends Controller{
 public function index(Request $r,TenantContext $ctx){
  $tenant=$ctx->requireTenant();$from=now()->subDays(max(1,min(365,(int)$r->integer('days',30))));
  $stats=[
   'event_views'=>AnalyticsEvent::where('tenant_id',$tenant->id)->where('event_name','event.view')->where('occurred_at','>=',$from)->count(),
   'rsvps'=>EventRsvp::whereHas('event',fn($q)=>$q->where('tenant_id',$tenant->id))->where('created_at','>=',$from)->count(),
   'checkins'=>EventCheckin::whereHas('event',fn($q)=>$q->where('tenant_id',$tenant->id))->where('checked_in_at','>=',$from)->count(),
   'orders'=>Order::where('tenant_id',$tenant->id)->where('created_at','>=',$from)->count(),
   'gross_cents'=>(int)Order::where('tenant_id',$tenant->id)->whereIn('status',['paid','completed'])->where('created_at','>=',$from)->sum('total_cents'),
  ];
  $events=Event::where('tenant_id',$tenant->id)->withCount('rsvps')->latest('starts_at')->limit(20)->get();
  return view('tenant.manage.analytics',compact('tenant','stats','events','from'));
 }
 public function export(TenantContext $ctx):StreamedResponse{
  $tenant=$ctx->requireTenant();$name='events-'.$tenant->slug.'-'.now()->format('Ymd').'.csv';
  return response()->streamDownload(function()use($tenant){$out=fopen('php://output','w');fputcsv($out,['Event','Starts','Status','Capacity','RSVPs']);Event::where('tenant_id',$tenant->id)->withCount('rsvps')->orderBy('starts_at')->chunk(200,function($events)use($out){foreach($events as $e)fputcsv($out,[$e->title,$e->starts_at?->toIso8601String(),$e->status,$e->capacity,$e->rsvps_count]);});fclose($out);},$name,['Content-Type'=>'text/csv']);
 }
}