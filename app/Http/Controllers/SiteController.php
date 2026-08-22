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

    public function events(Request $request, TenantContext $context, ?AffiliateAds $ads = null): View
    {
        $query = Event::query()->with('tenant')->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at');

        if ($context->check()) {
            $tenant = $context->requireTenant();
            $query->where('tenant_id', $tenant->id);
            $this->applyTenantVisibility($query, $tenant->id);
            $view = 'tenant.events.index';
        } else {
            $tenant = null;
            $ads ??= app(AffiliateAds::class);
            $query->where('visibility', 'public')
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true));
            $view = 'platform.events';
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('summary', 'like', '%'.$search.'%'));
        }
        if ($city = trim((string) $request->query('city'))) {
            $query->where('city', 'like', '%'.$city.'%');
        }
        if ($category = trim((string) $request->query('category'))) {
            $query->where('category', $category);
        }

        $events = $query->paginate(18)->withQueryString();
        $affiliateOffers = $tenant === null
            ? $ads->forPlacement('events', null, null, trim((string) $request->query('city', '')) ?: null, 3)
            : collect();

        return view($view, compact('tenant', 'events', 'affiliateOffers'));
    }

    public function about(TenantContext $context, PlatformContent $content): View
    {
        if (! $context->check()) {
            return view('platform.about', ['platformContent' => $content->all()]);
        }

        return $this->page($context, 'about');
    }

    public function organizations(TenantContext $context): View
    {
        abort_if($context->check(), 404);
        $organizations = Tenant::with(['primaryDomain', 'branding'])
            ->where('status', 'active')
            ->where('settings->marketplace_enabled', true)
            ->whereIn('type', [Tenant::TYPE_CLUB, Tenant::TYPE_ORGANIZER])
            ->orderBy('name')
            ->paginate(30);

        return view('platform.organizations', compact('organizations'));
    }

    public function page(TenantContext $context, string $slug): View
    {
        abort_unless($context->check(), 404);

        $tenant = $context->requireTenant()->load('branding');
        $page = CmsPage::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['sections' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')])
            ->firstOrFail();
        $events = $this->tenantDiscoverableEvents($tenant->id)
            ->orderBy('starts_at')
            ->limit(12)
            ->get();
        [$newsPosts, $membershipLevels, $communityPosts, $memberSpotlights] = $this->tenantHomepageCommunityData($tenant->id);

        return view('tenant.page', compact('tenant', 'page', 'events', 'newsPosts', 'membershipLevels', 'communityPosts', 'memberSpotlights'));
    }

    private function tenantHomepageCommunityData(int $tenantId): array
    {
        $isMember = auth()->check() && auth()->user()->tenants()->whereKey($tenantId)->wherePivot('status', 'active')->exists();
        $newsPosts = ClubNewsPost::query()->where('tenant_id', $tenantId)->where('status', 'published')->where('published_at', '<=', now())
            ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))->orderByDesc('is_pinned')->latest('published_at')->limit(6)->get();
        $membershipLevels = MembershipLevel::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort_order')->limit(8)->get();
        $communityPosts = collect();
        $memberSpotlights = collect();
        if ($isMember) {
            $communityPosts = CommunityPost::query()->where('tenant_id', $tenantId)->whereNull('group_id')->where('status', 'active')->where('share_to_club_wall', true)
                ->whereNull('event_id')->where('visibility', 'members')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->with('user:id,name,display_name')->latest()->limit(6)->get();
            $privacy = app(MemberPrivacy::class);
            $viewer = auth()->user();
            $memberSpotlights = User::query()->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId)->where('tenant_users.status', 'active'))
                ->where('users.id', '<>', $viewer->id)
                ->whereHas('profile', fn ($q) => $q->where('discoverable', true))->with('profile')->orderByDesc('last_login_at')->limit(20)->get()
                ->filter(fn (User $member) => $privacy->canViewProfile($tenantId, $viewer, $member))->take(8)->values();
        }
        return [$newsPosts, $membershipLevels, $communityPosts, $memberSpotlights];
    }

    private function tenantDiscoverableEvents(int $tenantId)
    {
        $query = Event::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('starts_at', '>=', now());
        $this->applyTenantVisibility($query, $tenantId);

        return $query;
    }

    private function applyTenantVisibility($query, int $tenantId): void
    {
        $query->whereIn(
            'visibility',
            TenantMembership::canAccessMembersContent(auth()->user(), $tenantId)
                ? ['public', 'members']
                : ['public']
        );
    }
}
