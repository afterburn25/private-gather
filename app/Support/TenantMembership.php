<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TenantMembershipTerm;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class TenantMembership
{
    public static function hasActiveMembership(?User $user, int $tenantId): bool
    {
        if (! $user) return false;

        $tenant = $user->tenants()->whereKey($tenantId)->first();
        $pivot = $tenant?->pivot;
        if (! $pivot || $pivot->status !== 'active') return false;

        // Operator roles are authorization roles, not paid/member tiers. A club
        // owner's operational access must never disappear because a member term expired.
        if ($pivot->role !== 'member') return true;

        // Backward compatibility: old members created before structured levels
        // remain active until a membership term is explicitly assigned.
        if (! Schema::hasTable('tenant_membership_terms')) return true;
        $term = TenantMembershipTerm::where('tenant_id', $tenantId)->where('user_id', $user->id)->first();
        if (! $term) return true;

        if ($term->expires_at && $term->expires_at->lte(now()) && in_array($term->status, [TenantMembershipTerm::STATUS_ACTIVE, TenantMembershipTerm::STATUS_GRACE], true)) {
            $term->update(['status' => TenantMembershipTerm::STATUS_EXPIRED, 'renews_at' => null]);
            return false;
        }

        return $term->isCurrentlyActive();
    }

    public static function canAccessMembersContent(?User $user, int $tenantId): bool
    {
        return (bool) ($user?->is_platform_admin) || self::hasActiveMembership($user, $tenantId);
    }
}
