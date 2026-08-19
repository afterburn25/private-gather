<?php
namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\Event;
use App\Models\Tenant;
use App\Services\PlatformContent;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function home(TenantContext $context, PlatformContent $content): View
    {
        if (! $context->check()) {
            return view('platform.home', [
                'platformContent' => $content->all(),
                'events' => Event::with('tenant')
                    ->whereHas('tenant', fn ($q) => $q->where('status', 'active')->where('settings->marketplace_enabled', true))
                    ->where('status', 'published')
                    ->where('visibility', 'public')
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(8)
                    ->get(),
                'organizations' => Tenant::with('primaryDomain')
                    ->where('status', 'active')
                    ->where('settings->marketplace_enabled', true)
                    ->whereIn('type', [Tenant::TYPE_CLUB, Tenant::TYPE_ORGANIZER])
                    ->latest()
                    ->limit(6)
                    ->get(),
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

        return view('tenant.home', compact('tenant', 'page', 'events'));
    }

    public function events(Request $request, TenantContext $context): View
    {
        $query = Event::query()->with('tenant')->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at');

        if ($context->check()) {
            $tenant = $context->requireTenant();
            $query->where('tenant_id', $tenant->id);
            $this->applyTenantVisibility($query, $tenant->id);
            $view = 'tenant.events.index';
        } else {
            $tenant = null;
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

        return view($view, compact('tenant', 'events'));
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
        $organizations = Tenant::with('primaryDomain')
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

        return view('tenant.page', compact('tenant', 'page', 'events'));
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
        // Members-only events belong to the organization, not merely to any
        // authenticated Private Gather account. Unlisted, invite-only, and
        // private events remain intentionally absent from discovery surfaces.
        $query->whereIn(
            'visibility',
            TenantMembership::canAccessMembersContent(auth()->user(), $tenantId)
                ? ['public', 'members']
                : ['public']
        );
    }
}
