<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class TenantMembership
{
    public static function canAccessMembersContent(?User $user, int $tenantId): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        return $user->tenants()
            ->whereKey($tenantId)
            ->wherePivot('status', 'active')
            ->exists();
    }
}
