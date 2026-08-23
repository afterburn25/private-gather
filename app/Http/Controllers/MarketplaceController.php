<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tenant;
use App\Services\ProductCompletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MarketplaceController extends Controller
{
    public function discover(Request $request, ProductCompletionService $products): View
    {
        $events = Event::query()->with('tenant')->withCount('rsvps')
            ->where('status','published')->where('visibility','public')->where('starts_at','>=',now())
            ->whereHas('tenant',fn($q)=>$q->where('status','active')->where('settings->marketplace_enabled',true));
        if ($q=trim((string)$request->query('q'))) $events->where(fn($x)=>$x->where('title','like','%'.$q.'%')->orWhere('summary','like','%'.$q.'%')->orWhere('description','like','%'.$q.'%'));
        if ($city=trim((string)$request->query('city'))) $events->where('city','like','%'.$city.'%');
        if ($category=trim((string)$request->query('category'))) $events->where('category',$category);
        if ($club=(int)$request->query('club')) $events->where('tenant_id',$club);
        $when=(string)$request->query('when');
        if ($when==='today') $events->whereBetween('starts_at',[now()->startOfDay(),now()->endOfDay()]);
        elseif ($when==='weekend') { $start=now()->next('Friday')->startOfDay(); if(now()->isFriday()||now()->isSaturday()||now()->isSunday()) $start=now()->startOfDay(); $events->whereBetween('starts_at',[$start,$start->copy()->next('Sunday')->endOfDay()]); }
        elseif ($when==='7days') $events->whereBetween('starts_at',[now(),now()->addDays(7)]);
        if ($from=$request->date('from')) $events->where('starts_at','>=',$from->startOfDay());
        if ($to=$request->date('to')) $events->where('starts_at','<=',$to->endOfDay());
        if (is_numeric($request->query('lat')) && is_numeric($request->query('lng')) && is_numeric($request->query('radius'))) {
            $lat=(float)$request->query('lat'); $lng=(float)$request->query('lng'); $radius=max(1,min(250,(float)$request->query('radius'))); $latDelta=$radius/69; $lngDelta=$radius/max(1,69*cos(deg2rad($lat)));
            $events->whereBetween('latitude',[$lat-$latDelta,$lat+$latDelta])->whereBetween('longitude',[$lng-$lngDelta,$lng+$lngDelta]);
        }
        match((string)$request->query('sort')) {
            'popular' => $events->orderByDesc('rsvps_count')->orderBy('starts_at'),
            'newest' => $events->latest('created_at'),
            default => $events->orderByRaw('CASE WHEN featured_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('featured_at')->orderBy('starts_at'),
        };
        $clubs=Tenant::query()->with('primaryDomain')->withCount(['events'=>fn($q)=>$q->where('status','published')->where('starts_at','>=',now())])
            ->where('status','active')->where('settings->marketplace_enabled',true)->whereIn('type',Tenant::publicNetworkTypes());
        if ($q=trim((string)$request->query('q'))) $clubs->where(fn($x)=>$x->where('name','like','%'.$q.'%')->orWhere('settings->marketplace_summary','like','%'.$q.'%'));
        if ($city=trim((string)$request->query('city'))) $clubs->where('settings->city','like','%'.$city.'%');
        return view('platform.discover',[
            'events'=>$events->paginate(18,['*'],'events_page')->withQueryString(),
            'clubs'=>$clubs->orderBy('name')->limit(12)->get(),
            'categories'=>Event::where('status','published')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'recommendations'=>$products->recommendations($request->user(),8),
        ]);
    }

    public function club(Tenant $tenant): View
    {
        abort_unless($tenant->isActive() && in_array($tenant->type,Tenant::publicNetworkTypes(),true) && (bool)data_get($tenant->settings,'marketplace_enabled'),404);
        $tenant->load(['primaryDomain','branding','membershipLevels'=>fn($q)=>$q->where('is_active',true)->orderBy('sort_order')]);
        $events=$tenant->events()->where('status','published')->where('visibility','public')->where('starts_at','>=',now())->orderBy('starts_at')->limit(12)->get();
        $reviews=DB::table('reviews')->join('users','users.id','=','reviews.user_id')->where('reviews.tenant_id',$tenant->id)->where('reviews.status','published')->select('reviews.*','users.display_name','users.name')->latest('reviews.created_at')->paginate(12);
        $rating=(float)(DB::table('reviews')->where('tenant_id',$tenant->id)->where('status','published')->avg('rating') ?? 0);
        $followed=auth()->check() && DB::table('user_follows')->where('user_id',auth()->id())->where('target_type','tenant')->where('target_id',$tenant->id)->exists();
        return view('platform.club',compact('tenant','events','reviews','rating','followed'));
    }
}
