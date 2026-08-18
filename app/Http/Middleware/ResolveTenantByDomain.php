<?php
namespace App\Http\Middleware;

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
            // Never reflect an attacker-controlled Host value into an error
            // page or allow malformed authority data to reach URL generation.
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
