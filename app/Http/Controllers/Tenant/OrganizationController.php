<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteController;
use App\Models\Tenant;
use App\Services\PlatformContent;
use App\Services\TenantProvisioner;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        return view('tenant.manage.create');
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        abort_unless(Edition::isHosted(), 404);

        // Human-friendly input such as "My Club" becomes a safe subdomain
        // instead of silently failing the lowercase-only validation rule.
        $request->merge([
            'subdomain' => Str::slug((string) $request->input('subdomain')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:club,organizer,private_host'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/'],
        ], [
            'subdomain.required' => 'Choose a Private Gather website address.',
            'subdomain.regex' => 'The website address may contain only letters, numbers, and hyphens.',
        ]);

        try {
            $tenant = $provisioner->create(
                $data['name'],
                $data['type'],
                $data['subdomain'],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'subdomain' => $exception->getMessage(),
            ]);
        }

        // Keep management usable on central/demo hosts where wildcard tenant
        // DNS is not available. Production tenant-domain requests still resolve
        // by Host and do not depend on this workspace session value.
        $request->session()->put('tenant.workspace_id', $tenant->id);

        return redirect()->route('tenant.dashboard')->with(
            'status',
            'Website created. You are now managing '.$tenant->name.'.',
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
