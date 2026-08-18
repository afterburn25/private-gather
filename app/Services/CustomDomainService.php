<?php
namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\DomainName;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CustomDomainService
{
    public function request(Tenant $tenant, string $domain): TenantDomain
    {
        $domain = DomainName::normalize($domain);
        $rootDomain = DomainName::normalize((string) config('platform.root_domain'));

        // Private Gather-owned hostnames are provisioned only through the
        // hosted-subdomain allocator, which enforces reserved names and global
        // uniqueness. The custom-domain path must never let a tenant claim an
        // arbitrary hostname inside the platform's own DNS namespace.
        if ($domain === $rootDomain || str_ends_with($domain, '.'.$rootDomain)) {
            throw new InvalidArgumentException('Private Gather platform domains cannot be claimed as custom domains.');
        }

        foreach ((array) config('platform.central_domains', []) as $central) {
            try {
                if ($domain === DomainName::normalize((string) $central)) {
                    throw new InvalidArgumentException('The platform domain cannot be claimed by a tenant.');
                }
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'The platform domain cannot be claimed by a tenant.') {
                    throw $e;
                }
            }
        }

        if (TenantDomain::where('domain', $domain)->exists()) {
            throw new InvalidArgumentException('That domain is already connected to an account.');
        }

        return $tenant->domains()->create([
            'domain' => $domain,
            'type' => substr_count($domain, '.') >= 2
                ? TenantDomain::TYPE_CUSTOM_SUBDOMAIN
                : TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => false,
            'status' => TenantDomain::STATUS_PENDING,
            'verification_token' => Str::random(48),
            'ssl_status' => 'pending',
            'dns_status' => 'pending',
            'redirect_to_primary' => false,
        ]);
    }

    public function dnsInstructions(TenantDomain $domain): array
    {
        return [
            'domain' => $domain->domain,
            'cname_target' => config('platform.domain_target'),
            'verification_record' => config('platform.domain_verification.txt_prefix').'.'.$domain->domain,
            'verification_value' => $domain->verification_token,
            'note' => 'For an apex/root domain, use the ALIAS/ANAME/CNAME-flattening option offered by the DNS provider, or configure the A/AAAA target supplied by the hosting edge.',
        ];
    }
}
