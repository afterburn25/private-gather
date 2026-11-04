<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class TenantMembership
{
    public static function hasActiveMembership(?User $user, int $tenantId): bool
    {
        if (! $user) {
            return false;
        }

        return $user->tenants()
            ->whereKey($tenantId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public static function canAccessMembersContent(?User $user, int $tenantId): bool
    {
        return (bool) ($user?->is_platform_admin)
            || self::hasActiveMembership($user, $tenantId);
    }
}
