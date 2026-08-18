<?php
namespace App\Services;

use App\Models\TenantDomain;
use Illuminate\Support\Facades\Cache;

final class TenantDomainCache
{
    public function find(string $host): ?TenantDomain
    {
        $key = 'tenant-domain:v2:'.hash('sha256', $host);
        $id = Cache::remember(
            $key,
            now()->addMinutes(5),
            fn () => TenantDomain::where('domain', $host)
                ->where('status', TenantDomain::STATUS_ACTIVE)
                ->whereNotNull('verified_at')
                ->value('id') ?: 0
        );

        if (! $id) {
            return null;
        }

        // Cache only the stable identifier. Authorization state is re-read on
        // every request so suspending/failing/unverifying a domain takes effect
        // immediately instead of leaving a five-minute stale access window.
        return TenantDomain::with('tenant')
            ->whereKey($id)
            ->where('domain', $host)
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->whereNotNull('verified_at')
            ->first();
    }

    public function forget(string $host): void
    {
        Cache::forget('tenant-domain:v1:'.hash('sha256', $host));
        Cache::forget('tenant-domain:v2:'.hash('sha256', $host));
    }
}
