<?php
namespace App\Http\Controllers;

use App\Models\ClubDirectoryProfile;
use App\Models\User;
use App\Models\MembershipLevel;
use App\Models\CommunityPost;
use App\Models\ClubNewsPost;
use App\Models\CmsPage;
use App\Models\Event;
use App\Models\Tenant;
use App\Services\AffiliateAds;
use App\Services\PlatformContent;
use App\Services\MemberPrivacy;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function home(TenantContext $context, PlatformContent $content, ?AffiliateAds $ads = null): View
    {
        if (! $context->check()) {
            $ads ??= app(AffiliateAds::class);

            $events = Event::with('tenant')
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true))
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(8)
                ->get();

            $organizations = Tenant::with(['primaryDomain', 'branding'])
                ->where('status', 'active')
                ->where('settings->marketplace_enabled', true)
                ->whereIn('type', [Tenant::TYPE_CLUB, Tenant::TYPE_ORGANIZER])
                ->latest()
                ->limit(6)
                ->get();

            $featuredClubs = ClubDirectoryProfile::query()
                ->with(['tenant.primaryDomain', 'tenant.branding'])
                ->where('is_listed', true)
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('type', Tenant::TYPE_CLUB))
                ->orderByRaw('CASE WHEN featured_until IS NOT NULL AND featured_until > ? THEN 0 ELSE 1 END', [now()])
                ->orderByRaw('CASE WHEN verified_at IS NOT NULL THEN 0 ELSE 1 END')
                ->orderBy('listing_name')
                ->limit(6)
                ->get();

            $directoryStats = [
                'clubs' => ClubDirectoryProfile::query()->where('is_listed', true)->count(),
                'cities' => ClubDirectoryProfile::query()->where('is_listed', true)->whereNotNull('city')->distinct()->count('city'),
                'events' => Event::query()
                    ->where('status', 'published')
                    ->where('visibility', 'public')
                    ->where('starts_at', '>=', now())
                    ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true))
                    ->count(),
            ];

            $eventCategories = Event::query()
                ->selectRaw('category, COUNT(*) as total')
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->where('starts_at', '>=', now())
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true))
                ->groupBy('category')
                ->orderByDesc('total')
                ->limit(6)
                ->get();

            return view('platform.home', [
                'platformContent' => $content->all(),
                'events' => $events,
                'organizations' => $organizations,
                'featuredClubs' => $featuredClubs,
                'directoryStats' => $directoryStats,
                'eventCategories' => $eventCategories,
                'affiliateOffers' => $ads->forPlacement('home', null, null, null, 3),
            ]);
        }

        $tenant = $context->requireTenant()->load('branding');
        $page = CmsPage::where('tenant_id', $tenant->id)
            ->where('is_homepage', true)
            ->where('status', 'published')
            ->with(['sections' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')])
            ->first();
        $events = $this->tenantDiscoverableEvents($tenant->id)
            ->orderBy('starts_at')
            ->limit(12)
            ->get();
        [$newsPosts, $membershipLevels, $communityPosts, $memberSpotlights] = $this->tenantHomepageCommunityData($tenant->id);

        return view('tenant.home', compact('tenant', 'page', 'events', 'newsPosts', 'membershipLevels', 'communityPosts', 'memberSpotlights'));
    }

    public function events(TenantContext $context): View
    {
        if (! $context->check()) {
            $events = Event::with('tenant')
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true))
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->paginate(24);

            return view('platform.events', compact('events'));
        }

        $tenant = $context->requireTenant();
        $events = $this->tenantDiscoverableEvents($tenant->id)
            ->orderBy('starts_at')
            ->paginate(24);

        return view('tenant.events.index', compact('tenant', 'events'));
    }

    public function organizations(): View
    {
        $organizations = Tenant::with(['primaryDomain', 'branding'])
            ->where('status', 'active')
            ->where('settings->marketplace_enabled', true)
            ->whereIn('type', [Tenant::TYPE_CLUB, Tenant::TYPE_ORGANIZER])
            ->orderBy('name')
            ->paginate(24);

        return view('platform.organizations', compact('organizations'));
    }

    public function about(TenantContext $context): View
    {
        if (! $context->check()) {
            return view('platform.about');
        }

        $tenant = $context->requireTenant();

        return view('tenant.about', compact('tenant'));
    }

    public function page(Request $request, TenantContext $context, string $slug): View
    {
        if ($context->check()) {
            $tenant = $context->requireTenant();
            $page = CmsPage::where('tenant_id', $tenant->id)
                ->where('slug', $slug)
                ->where('status', 'published')
                ->with(['sections' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')])
                ->firstOrFail();

            return view('tenant.cms.page', compact('tenant', 'page'));
        }

        $slug = strtolower(trim($slug));
        if ($slug === 'community') {
            return app(\App\Http\Controllers\Member\CommunityController::class)->platform($request);
        }
        if ($slug === 'network') {
            return app(\App\Http\Controllers\Member\HostedNetworkController::class)->platform($request);
        }
        if ($slug === 'messages') {
            return app(\App\Http\Controllers\Member\MessageController::class)->platform($request);
        }

        return view('platform.page', ['slug' => $slug]);
    }

    private function tenantDiscoverableEvents(int $tenantId)
    {
        return Event::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('starts_at', '>=', now());
    }

    private function tenantHomepageCommunityData(int $tenantId): array
    {
        $newsPosts = ClubNewsPost::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->latest('published_at')
            ->limit(3)
            ->get();
        $membershipLevels = MembershipLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('position')
            ->limit(4)
            ->get();
        $communityPosts = CommunityPost::query()
            ->where('tenant_id', $tenantId)
            ->where('visibility', 'club')
            ->latest()
            ->limit(3)
            ->get();
        $memberSpotlights = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId)->where('tenant_users.status', 'active'))
            ->whereHas('profile', fn ($q) => $q->where('visibility', 'public')->where('is_discoverable', true))
            ->with('profile')
            ->limit(4)
            ->get();

        return [$newsPosts, $membershipLevels, $communityPosts, $memberSpotlights];
    }
}
