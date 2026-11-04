<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteController;
use App\Models\Tenant;
use App\Services\PlatformContent;
use App\Services\TenantProvisioner;
use App\Support\Edition;
use App\Support\ThemeCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrganizationController extends Controller
{
    public function index(
        Request $request,
        TenantContext $context,
        SiteController $site,
        PlatformContent $content,
    ): ViewContract|RedirectResponse {
        if (Edition::isSelfHosted()) {
            $tenant = $context->requireTenant();
            $this->authorizeManagement($request, $tenant);
            $request->session()->put('tenant.workspace_id', $tenant->id);

            return redirect()->route('tenant.dashboard');
        }

        if ($workspaceId = (int) $request->query('workspace', 0)) {
            $tenant = $this->tenantForUser($request, $workspaceId);
            $this->authorizeManagement($request, $tenant);
            $request->session()->put('tenant.workspace_id', $tenant->id);

            return redirect()->route('tenant.dashboard');
        }

        if ($previewId = (int) $request->query('preview', 0)) {
            $tenant = $this->tenantForUser($request, $previewId);
            $this->authorizeManagement($request, $tenant);
            abort_unless($tenant->isActive(), 404);

            $context->set($tenant);
            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('tenant_preview', true);

            return $site->home($context, $content);
        }

        return view('tenant.manage.organizations', [
            'tenants' => $request->user()->tenants()->with('domains')->get(),
        ]);
    }

    public function create(): ViewContract
    {
        abort_unless(Edition::isHosted(), 404);

        return view('tenant.manage.create', [
            'tenantThemes' => ThemeCatalog::tenantThemes(),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        abort_unless(Edition::isHosted(), 404);

        // Human-friendly input such as "My Club" becomes a safe subdomain
        // instead of silently failing lowercase-only validation.
        $request->merge([
            'subdomain' => Str::slug((string) $request->input('subdomain')),
        ]);

        $legacyThemes = [
            'midnight' => 'velvet-lounge',
            'velvet' => 'velvet-lounge',
            'noir' => 'gallery-minimal',
            'modern' => 'social-club',
        ];
        $requestedTheme = trim((string) $request->input('theme'));
        if ($requestedTheme === '') {
            $requestedTheme = $legacyThemes[(string) $request->input('template')] ?? ThemeCatalog::TENANT_DEFAULT;
        }
        $request->merge(['theme' => $requestedTheme]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:club,organization,organizer,private_host'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'theme' => ['required', Rule::in(array_keys(ThemeCatalog::tenantThemes()))],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'minimum_age' => ['nullable', 'integer', 'between:18,99'],
            'membership_access' => ['nullable', 'in:application,open,invite_only'],
            'membership_profiles' => ['nullable', 'array', 'min:1'],
            'membership_profiles.*' => ['required', Rule::in(['couple', 'individual'])],
            'event_access_default' => ['nullable', 'in:public,members,approval,invite_only'],
            'venue_address_visibility' => ['nullable', 'in:public,members,approved_or_ticketed'],
            'network_visibility' => ['nullable', 'in:listed,private,invite_only'],
            'member_directory_visibility' => ['nullable', 'in:disabled,members,verified,public'],
            'event_attendance_visibility' => ['nullable', 'in:private,members,attendees'],
            'custom_domain' => ['nullable', 'string', 'max:253'],
            'marketplace_enabled' => ['nullable', 'boolean'],
        ], [
            'subdomain.required' => 'Choose a Private Gather website address.',
            'subdomain.regex' => 'The website address may contain only letters, numbers, and hyphens.',
            'theme.in' => 'Choose one of the approved Private Gather tenant themes.',
            'membership_profiles.min' => 'Choose at least one membership profile type.',
        ]);

        $networkVisibility = $data['network_visibility'] ?? null;
        if ($networkVisibility === null) {
            if (($data['type'] ?? null) === Tenant::TYPE_PRIVATE_HOST) {
                $networkVisibility = 'private';
            } else {
                $networkVisibility = $request->exists('marketplace_enabled') && ! $request->boolean('marketplace_enabled')
                    ? 'private'
                    : 'listed';
            }
        }

        // Private-host creation is intentionally fail-closed. Owners may change
        // discovery later from Club OS, but first launch must not accidentally
        // expose an invitation-focused host because of a stale/default form value.
        if (($data['type'] ?? null) === Tenant::TYPE_PRIVATE_HOST && $networkVisibility === 'listed') {
            $networkVisibility = 'private';
        }

        $siteOptions = [
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'theme' => $data['theme'],
            'primary_color' => $data['primary_color'] ?? '#171319',
            'accent_color' => $data['accent_color'] ?? '#9A3456',
            'minimum_age' => $data['minimum_age'] ?? 18,
            'membership_access' => $data['membership_access'] ?? 'application',
            'membership_profiles' => $data['membership_profiles'] ?? ['couple', 'individual'],
            'event_access_default' => $data['event_access_default'] ?? 'members',
            'venue_address_visibility' => $data['venue_address_visibility'] ?? 'approved_or_ticketed',
            'network_visibility' => $networkVisibility,
            'marketplace_enabled' => $networkVisibility === 'listed',
            'member_directory_visibility' => $data['member_directory_visibility'] ?? 'members',
            'event_attendance_visibility' => $data['event_attendance_visibility'] ?? 'members',
            'custom_domain' => trim((string) ($data['custom_domain'] ?? '')),
        ];

        if (filled($data['tagline'] ?? null)) {
            $siteOptions['tagline'] = trim($data['tagline']);
        }

        try {
            $tenant = $provisioner->create(
                $data['name'],
                $data['type'],
                $data['subdomain'],
                $request->user(),
                $siteOptions,
            );
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $field = str_contains(strtolower($message), 'domain') && ! str_contains(strtolower($message), 'subdomain')
                ? 'custom_domain'
                : 'subdomain';
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        // Keep management usable on central/demo hosts where wildcard tenant
        // DNS is not available. Production tenant-domain requests still resolve
        // by Host and do not depend on this workspace session value.
        $request->session()->put('tenant.workspace_id', $tenant->id);

        $customDomainPending = $tenant->domains
            ->contains(fn ($domain) => $domain->type !== 'platform_subdomain' && $domain->status !== 'active');

        return redirect()->route('tenant.dashboard')->with(
            'status',
            ($tenant->isClub()
                ? 'Club website created. You are now managing '.$tenant->name.'.'
                : 'Organization website created. You are now managing '.$tenant->name.'.')
            .($customDomainPending ? ' Your custom domain was added and is waiting for DNS verification.' : ''),
        );
    }

    private function tenantForUser(Request $request, int $tenantId): Tenant
    {
        if ($request->user()?->is_platform_admin) {
            return Tenant::query()->findOrFail($tenantId);
        }

        return $request->user()->tenants()->whereKey($tenantId)->firstOrFail();
    }

    private function authorizeManagement(Request $request, Tenant $tenant): void
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_platform_admin) {
            return;
        }

        $membership = $user->tenants()->whereKey($tenant->id)->first()?->pivot;
        abort_unless(
            $membership
                && in_array($membership->role, ['owner', 'admin', 'manager'], true)
                && $membership->status === 'active',
            403,
        );
    }
}
