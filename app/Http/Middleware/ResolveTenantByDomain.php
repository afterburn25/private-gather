<?php
namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantDomainCache;
use App\Support\DomainName;
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
}
