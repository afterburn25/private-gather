<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\AffiliateOffer;
use App\Models\ClubDirectoryProfile;
use App\Models\Event;
use App\Models\Tenant;
use App\Services\AffiliateAds;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class HostedDiscoveryController extends Controller
{
    public function clubs(Request $request, TenantContext $context, AffiliateAds $ads): View
    {
        abort_if($context->check(), 404);

        $query = ClubDirectoryProfile::query()
            ->with(['tenant.primaryDomain', 'tenant.branding'])
            ->where('is_listed', true)
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('type', Tenant::TYPE_CLUB));

        $search = trim((string) $request->query('q', ''));
        $country = strtoupper(trim((string) $request->query('country', '')));
        $region = trim((string) $request->query('region', ''));
        $city = trim((string) $request->query('city', ''));
        $clubType = trim((string) $request->query('type', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('listing_name', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'like', '%'.$search.'%'));
            });
        }
        if ($country !== '') $query->where('country_code', $country);
        if ($region !== '') $query->where('region', $region);
        if ($city !== '') $query->where('city', $city);
        if ($clubType !== '') $query->where('club_type', $clubType);

        $clubs = $query
            ->orderByRaw('CASE WHEN featured_until IS NOT NULL AND featured_until > ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('region')->orderBy('city')->orderBy('listing_name')
            ->paginate(24)->withQueryString();

        $filters = [
            'countries' => ClubDirectoryProfile::query()->where('is_listed', true)->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code'),
            'regions' => ClubDirectoryProfile::query()->where('is_listed', true)->when($country !== '', fn ($q) => $q->where('country_code', $country))->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
            'cities' => ClubDirectoryProfile::query()->where('is_listed', true)->when($country !== '', fn ($q) => $q->where('country_code', $country))->when($region !== '', fn ($q) => $q->where('region', $region))->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'types' => ClubDirectoryProfile::query()->where('is_listed', true)->distinct()->orderBy('club_type')->pluck('club_type'),
        ];

        $affiliateOffers = $ads->forPlacement('club_directory', $country ?: null, $region ?: null, $city ?: null, 3);
        $membershipStatuses = collect();
        if ($request->user() && $clubs->isNotEmpty()) {
            $membershipStatuses = DB::table('tenant_users')
                ->where('user_id', $request->user()->id)
                ->whereIn('tenant_id', $clubs->getCollection()->pluck('tenant_id')->all())
                ->pluck('status', 'tenant_id');
        }

        return view('platform.clubs.index', compact('clubs', 'filters', 'affiliateOffers', 'membershipStatuses'));
    }

    public function club(Request $request, string $slug, TenantContext $context, AffiliateAds $ads): View
    {
        abort_if($context->check(), 404);

        $tenant = Tenant::query()->where('slug', $slug)->where('type', Tenant::TYPE_CLUB)->where('status', 'active')
            ->with(['primaryDomain', 'branding'])->firstOrFail();

        $profile = ClubDirectoryProfile::query()->where('tenant_id', $tenant->id)->where('is_listed', true)->firstOrFail();

        $events = Event::query()->where('tenant_id', $tenant->id)->where('status', 'published')->where('visibility', 'public')
            ->where('starts_at', '>=', now())->orderBy('starts_at')->limit(8)->get();

        $affiliateOffers = $ads->forPlacement('club_detail', $profile->country_code, $profile->region, $profile->city, 2);
        $membershipStatus = $request->user()
            ? DB::table('tenant_users')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->value('status')
            : null;

        return view('platform.clubs.show', compact('tenant', 'profile', 'events', 'affiliateOffers', 'membershipStatus'));
    }

    public function affiliate(Request $request, AffiliateOffer $offer): RedirectResponse
    {
        abort_unless($offer->isLive(), 404);
        $url = trim((string) $offer->affiliate_url);
        $parts = parse_url($url);
        abort_unless(is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && ! empty($parts['host']), 404);

        $appKey = (string) config('app.key', 'private-gather');
        $placement = Str::limit(trim((string) $request->query('placement', 'unknown')), 80, '');
        $referrerHost = parse_url((string) $request->headers->get('referer', ''), PHP_URL_HOST);

        AffiliateClick::create([
            'affiliate_offer_id' => $offer->id,
            'user_id' => $request->user()?->id,
            'tenant_id' => app(TenantContext::class)->id(),
            'placement' => $placement !== '' ? $placement : null,
            'referrer_host' => is_string($referrerHost) ? Str::limit(strtolower($referrerHost), 255, '') : null,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', (string) $request->ip(), $appKey) : null,
            'user_agent_hash' => $request->userAgent() ? hash_hmac('sha256', (string) $request->userAgent(), $appKey) : null,
            'clicked_at' => now(),
        ]);

        return redirect()->away($url, 302, ['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store, private']);
    }
}
