<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantDomain;
use App\Services\CustomDomainService;
use App\Services\DomainHealthService;
use App\Services\TenantDomainCache;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    public function index(TenantContext $context, CustomDomainService $service)
    {
        $tenant = $context->requireTenant()->load(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')]);
        return view('tenant.manage.domains', ['tenant' => $tenant, 'domains' => $tenant->domains, 'service' => $service]);
    }

    public function store(Request $request, TenantContext $context, CustomDomainService $service)
    {
        $data = $request->validate(['domain' => 'required|string|max:253']);
        $domain = $service->request($context->requireTenant(), $data['domain']);
        Audit::write('domain.requested', $domain, tenantId: $domain->tenant_id, request: $request);
        return back()->with('status', 'Domain added. Add the ownership and routing DNS records shown below, then run diagnostics.');
    }

    public function verify(Request $request, TenantContext $context, TenantDomain $domain, DomainHealthService $health, TenantDomainCache $cache)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        $result = $health->verify($domain);
        $cache->forget($domain->domain);
        Audit::write('domain.verified_check', $domain, null, ['verified' => $result['verified'], 'dns_status' => $result['dns_status'] ?? null], $tenant->id, $request);
        if (! $result['verified']) return back()->with('status', 'Ownership TXT record is not visible yet. DNS observations were refreshed.');
        if (! ($result['routing_ready'] ?? false)) return back()->with('status', 'Ownership verified. Website routing still needs to reach the configured Private Gather target.');
        return back()->with('status', 'Domain ownership and routing are visible. HTTPS provisioning was requested; SSL remains pending until the provider or live certificate confirms it.');
    }

    public function diagnose(Request $request, TenantContext $context, TenantDomain $domain, DomainHealthService $health, TenantDomainCache $cache)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        $result = $health->diagnoseDns($domain);
        $cache->forget($domain->domain);
        Audit::write('domain.diagnostics.refreshed', $domain, null, ['dns_status' => $result['dns_status'], 'routing_ready' => $result['routing_ready']], $tenant->id, $request);
        return back()->with('status', 'DNS diagnostics refreshed. The status below reflects records visible to this server now.');
    }

    public function provision(Request $request, TenantContext $context, TenantDomain $domain, DomainHealthService $health)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        abort_unless($domain->status === TenantDomain::STATUS_ACTIVE && $domain->verified_at, 422, 'Verify domain ownership before retrying HTTPS provisioning.');
        abort_unless(in_array((string) $domain->dns_status, ['ready', 'active'], true), 422, 'Confirm website routing to the Hosted edge before retrying HTTPS provisioning.');
        $result = $health->retryProvision($domain);
        Audit::write('domain.provisioning.retried', $domain, null, ['accepted' => (bool) ($result['accepted'] ?? false), 'ssl_status' => $result['ssl_status'] ?? null], $tenant->id, $request);
        return back()->with('status', (bool) ($result['accepted'] ?? false)
            ? 'HTTPS provisioning request accepted by the configured domain provider. Certificate state remains separate until confirmed.'
            : 'HTTPS provisioning was not accepted. Review the domain error and provider configuration below.');
    }

    public function sslCheck(Request $request, TenantContext $context, TenantDomain $domain, DomainHealthService $health)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        $result = $health->inspectSsl($domain);
        Audit::write('domain.ssl.checked', $domain, null, ['status' => $result['status'] ?? null, 'reachable' => $result['reachable'] ?? false], $tenant->id, $request);
        return back()->with('status', ($result['reachable'] ?? false)
            ? 'Live TLS certificate check completed. Expiry and state are shown below.'
            : 'Live TLS certificate could not be confirmed from this server. Review the recorded error before retrying.');
    }

    public function redirect(Request $request, TenantContext $context, TenantDomain $domain, TenantDomainCache $cache)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        abort_if($domain->is_primary && $request->boolean('redirect_to_primary'), 422, 'The primary domain cannot redirect to itself.');
        abort_unless($domain->status === TenantDomain::STATUS_ACTIVE, 422, 'Only active domains can redirect to the primary domain.');
        $enabled = $request->boolean('redirect_to_primary');
        $domain->update(['redirect_to_primary' => $enabled]);
        $cache->forget($domain->domain);
        Audit::write('domain.redirect.updated', $domain, null, ['redirect_to_primary' => $enabled], $tenant->id, $request);
        return back()->with('status', $enabled ? 'This domain will redirect to the current primary domain.' : 'Primary-domain redirect disabled for this domain.');
    }

    public function primary(Request $request, TenantContext $context, TenantDomain $domain, TenantDomainCache $cache)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        abort_unless($domain->status === TenantDomain::STATUS_ACTIVE && $domain->verified_at, 422, 'Only a verified active domain can become primary.');
        $domains = $tenant->domains()->get();
        DB::transaction(function () use ($tenant, $domain): void {
            TenantDomain::query()->where('tenant_id', $tenant->id)->lockForUpdate()->get();
            $tenant->domains()->update(['is_primary' => false]);
            $domain->update(['is_primary' => true, 'redirect_to_primary' => false]);
        });
        foreach ($domains as $known) $cache->forget($known->domain);
        Audit::write('domain.primary.updated', $domain, null, ['is_primary' => true], $tenant->id, $request);
        return back()->with('status', 'Primary domain updated. Existing redirect choices continue to point at whichever domain is primary now.');
    }

    public function destroy(Request $request, TenantContext $context, TenantDomain $domain, TenantDomainCache $cache)
    {
        $tenant = $context->requireTenant();
        $this->assertDomain($domain, $tenant->id);
        abort_if($domain->is_primary, 422, 'Choose another verified active primary domain before disconnecting this one.');
        abort_if($domain->type === TenantDomain::TYPE_PLATFORM_SUBDOMAIN, 422, 'The hosted Private Gather subdomain is managed by the platform and cannot be disconnected here.');
        $host = $domain->domain;
        Audit::write('domain.disconnected', $domain, $domain->toArray(), null, $tenant->id, $request);
        $domain->delete();
        $cache->forget($host);
        return back()->with('status', 'Custom domain disconnected from this organization. Registrar and DNS records were not deleted.');
    }

    private function assertDomain(TenantDomain $domain, int $tenantId): void
    {
        abort_unless((int) $domain->tenant_id === $tenantId, 404);
    }
}
