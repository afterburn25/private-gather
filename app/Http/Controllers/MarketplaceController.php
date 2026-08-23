<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tenant;
use App\Services\ProductCompletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class MarketplaceController extends Controller
{
    public function discover(Request $request, ProductCompletionService $products): View
    {
        $events = Event::query()->with('tenant')->withCount('rsvps')
            ->where('status', 'published')->where('visibility', 'public')->where('starts_at', '>=', now())
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true));
        if ($q = trim((string) $request->query('q'))) $events->where(fn ($x) => $x->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%')->orWhere('description', 'like', '%'.$q.'%'));
        if ($city = trim((string) $request->query('city'))) $events->where('city', 'like', '%'.$city.'%');
        if ($category = trim((string) $request->query('category'))) $events->where('category', $category);
        if ($club = (int) $request->query('club')) $events->where('tenant_id', $club);
        $when = (string) $request->query('when');
        if ($when === 'today') $events->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()]);
        elseif ($when === 'weekend') {
            $start = now()->next('Friday')->startOfDay();
            if (now()->isFriday() || now()->isSaturday() || now()->isSunday()) $start = now()->startOfDay();
            $events->whereBetween('starts_at', [$start, $start->copy()->next('Sunday')->endOfDay()]);
        } elseif ($when === '7days') $events->whereBetween('starts_at', [now(), now()->addDays(7)]);
        if ($from = $request->date('from')) $events->where('starts_at', '>=', $from->startOfDay());
        if ($to = $request->date('to')) $events->where('starts_at', '<=', $to->endOfDay());
        if (is_numeric($request->query('lat')) && is_numeric($request->query('lng')) && is_numeric($request->query('radius'))) {
            $lat = (float) $request->query('lat'); $lng = (float) $request->query('lng'); $radius = max(1, min(250, (float) $request->query('radius')));
            $latDelta = $radius / 69; $lngDelta = $radius / max(1, 69 * cos(deg2rad($lat)));
            $events->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
        }
        match ((string) $request->query('sort')) {
            'popular' => $events->orderByDesc('rsvps_count')->orderBy('starts_at'),
            'newest' => $events->latest('created_at'),
            default => $events->orderByRaw('CASE WHEN featured_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('featured_at')->orderBy('starts_at'),
        };
        $clubs = Tenant::query()->with('primaryDomain')->withCount(['events' => fn ($q) => $q->where('status', 'published')->where('starts_at', '>=', now())])
            ->where('status', 'active')->where('settings->marketplace_enabled', true)->whereIn('type', Tenant::publicNetworkTypes());
        if ($q = trim((string) $request->query('q'))) $clubs->where(fn ($x) => $x->where('name', 'like', '%'.$q.'%')->orWhere('settings->marketplace_summary', 'like', '%'.$q.'%'));
        if ($city = trim((string) $request->query('city'))) $clubs->where('settings->city', 'like', '%'.$city.'%');
        return view('platform.discover', [
            'events' => $events->paginate(18, ['*'], 'events_page')->withQueryString(),
            'clubs' => $clubs->orderBy('name')->limit(12)->get(),
            'categories' => Event::where('status', 'published')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'recommendations' => $products->recommendations($request->user(), 8),
        ]);
    }

    public function club(Tenant $tenant): View
    {
        abort_unless($tenant->isActive() && in_array($tenant->type, Tenant::publicNetworkTypes(), true) && (bool) data_get($tenant->settings, 'marketplace_enabled'), 404);
        $tenant->load(['primaryDomain', 'branding', 'membershipLevels' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);
        $events = $tenant->events()->where('status', 'published')->where('visibility', 'public')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(12)->get();
        $reviews = DB::table('reviews')->join('users', 'users.id', '=', 'reviews.user_id')->where('reviews.tenant_id', $tenant->id)->where('reviews.status', 'published')->select('reviews.*', 'users.display_name', 'users.name')->latest('reviews.created_at')->paginate(12);
        $rating = (float) (DB::table('reviews')->where('tenant_id', $tenant->id)->where('status', 'published')->avg('rating') ?? 0);
        $followed = auth()->check() && DB::table('user_follows')->where('user_id', auth()->id())->where('target_type', 'tenant')->where('target_id', $tenant->id)->exists();
        return view('platform.club', compact('tenant', 'events', 'reviews', 'rating', 'followed'));
    }

    public function calendar(Event $event): Response
    {
        abort_unless($event->status === 'published' && in_array($event->visibility, ['public', 'unlisted'], true), 404);
        $event->loadMissing('tenant');
        $escape = static fn (?string $value): string => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", '\\;', '\\,', '', '\\n'], (string) $value);
        $location = $event->exact_address_visibility === 'public' && $event->exact_address
            ? $event->exact_address
            : ($event->public_location_label ?: trim($event->city.', '.$event->region, ', '));
        $start = $event->starts_at?->copy()->utc()->format('Ymd\THis\Z');
        $end = ($event->ends_at ?: $event->starts_at?->copy()->addHours(3))?->copy()->utc()->format('Ymd\THis\Z');
        $body = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Private Gather//Events//EN', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT', 'UID:private-gather-event-'.$event->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'), 'DTSTART:'.$start, 'DTEND:'.$end,
            'SUMMARY:'.$escape($event->title), 'DESCRIPTION:'.$escape($event->summary ?: $event->description),
            'LOCATION:'.$escape($location ?: 'Location shared by host according to event privacy settings.'),
            'URL:'.$escape(route('events.show', $event)), 'END:VEVENT', 'END:VCALENDAR', '',
        ]);
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="private-gather-event-'.$event->id.'.ics"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function referral(Request $request, string $code): RedirectResponse
    {
        $referral = DB::table('referral_codes')->where('code', strtoupper($code))->where('active', true)->first();
        abort_unless($referral, 404);
        $request->session()->put('private_gather_referral_code_id', (int) $referral->id);
        $request->session()->put('private_gather_referral_code', $referral->code);
        DB::table('referral_attributions')->insert([
            'referral_code_id' => $referral->id, 'user_id' => $request->user()?->id,
            'tenant_id' => $referral->tenant_id, 'event_id' => null, 'order_id' => null,
            'session_key' => hash('sha256', $request->session()->getId()), 'attributed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($referral->tenant_id) {
            $tenant = Tenant::find($referral->tenant_id);
            if ($tenant && $tenant->isActive() && (bool) data_get($tenant->settings, 'marketplace_enabled')) return redirect()->route('clubs.show', $tenant);
        }
        return redirect()->route('discover.index')->with('status', 'Referral attribution recorded.');
    }
}
