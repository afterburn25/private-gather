<?php
namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainCache;
use App\Support\DomainName;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantByDomain
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantDomainCache $domains,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $host = DomainName::normalize($request->getHost());
        } catch (InvalidArgumentException|SuspiciousOperationException) {
            abort(400, 'Invalid request host.');
        }

        if (Edition::isSelfHosted()) {
            $tenant = $this->selfHostedTenant();
            if (! $tenant || ! $tenant->isActive()) {
                $this->context->clear();
                return response('Private Gather Self-Hosted is not configured with an active organization.', 503);
            }

            $this->context->set($tenant);
            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('tenant_self_hosted', true);
            $request->attributes->set('request_host', $host);

            return $next($request);
        }

        $centralDomains = [];
        foreach ((array) config('platform.central_domains', []) as $candidate) {
            try {
                $centralDomains[] = DomainName::normalize((string) $candidate);
            } catch (InvalidArgumentException) {
                // Invalid deployment configuration must not expand the set of
                // trusted hosts. It is simply excluded from request matching.
            }
        }
        $centralDomains = array_values(array_unique($centralDomains));

        if (in_array($host, $centralDomains, true)) {
            $this->context->clear();

            // Central/demo installs may not have wildcard tenant DNS available.
            // For authenticated management routes only, restore the tenant that
            // the user explicitly selected from My Sites. Authorization remains
            // enforced by EnsureTenantManager / EnsureTenantStaff on every route.
            if ($request->is('manage') || $request->is('manage/*')) {
                $workspaceId = (int) $request->session()->get('tenant.workspace_id', 0);
                if ($workspaceId > 0) {
                    $tenant = Tenant::query()->find($workspaceId);
                    if ($tenant && $tenant->isActive()) {
                        $this->context->set($tenant);
                        $request->attributes->set('tenant', $tenant);
                        $request->attributes->set('tenant_workspace', true);
                    } else {
                        $request->session()->forget('tenant.workspace_id');
                    }
                }
            }

            return $next($request);
        }

        $domain = $this->domains->find($host);

        // Platform subdomains are owned by Private Gather itself, so they do not
        // require external-domain ownership verification. If a request for a
        // recorded platform subdomain reaches this Laravel application, DNS and
        // web routing are demonstrably working. Promote a pending platform
        // subdomain at that point instead of returning a permanent 404 until a
        // human clicks Provision / Repair again.
        if (! $domain && str_ends_with($host, '.'.(string) config('platform.root_domain'))) {
            $domain = TenantDomain::with('tenant')
                ->where('domain', $host)
                ->where('type', TenantDomain::TYPE_PLATFORM_SUBDOMAIN)
                ->whereIn('status', [TenantDomain::STATUS_PENDING, TenantDomain::STATUS_ACTIVE])
                ->first();

            if ($domain && $domain->tenant?->isActive()) {
                if ($domain->status !== TenantDomain::STATUS_ACTIVE || ! $domain->verified_at || $domain->dns_status !== 'active') {
                    $health = is_array($domain->health) ? $domain->health : [];
                    $domain->forceFill([
                        'status' => TenantDomain::STATUS_ACTIVE,
                        'verified_at' => $domain->verified_at ?: now(),
                        'dns_status' => 'active',
                        'dns_last_checked_at' => now(),
                        'last_error' => null,
                        'health' => array_merge($health, [
                            'provisioner' => $health['provisioner'] ?? 'wildcard-request-route',
                            'accepted' => true,
                            'route_confirmed_at' => now()->toIso8601String(),
                        ]),
                    ])->save();
                    $this->domains->forget($host);
                }
            }
        }

        if (! $domain || ! $domain->tenant || ! $domain->tenant->isActive()) {
            $this->context->clear();
            return response()->view('errors.tenant-domain', [
                'host' => $host,
                'domainTarget' => config('platform.domain_target'),
            ], 404);
        }

        $this->context->set($domain->tenant);
        $request->attributes->set('tenant', $domain->tenant);
        $request->attributes->set('tenant_domain', $domain);

        return $next($request);
    }

    private function selfHostedTenant(): ?Tenant
    {
        $query = Tenant::query()->where('status', 'active');

        if ($tenantId = Edition::selfHostedTenantId()) {
            return $query->whereKey($tenantId)->first();
        }

        return $query->orderBy('id')->first();
    }
}
