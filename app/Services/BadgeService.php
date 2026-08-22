<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BadgeService
{
    public const RESERVED_TENANT_NAMES = [
        'private gather',
        'private gather verified',
        'official',
        'official private gather',
        'platform staff',
        'platform moderator',
        'identity verified',
        'age verified',
        'phone verified',
        'email verified',
    ];

    public function tenantNameIsReserved(string $name): bool
    {
        $normalized = Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return in_array($normalized, self::RESERVED_TENANT_NAMES, true)
            || str_starts_with($normalized, 'private gather ');
    }

    public function syncAllForUser(User $user): void
    {
        $this->syncForUser($user, null);
        $tenantIds = $user->tenants()->wherePivot('status', 'active')->pluck('tenants.id');
        foreach ($tenantIds as $tenantId) {
            $this->syncForUser($user, (int) $tenantId);
        }
    }

    public function syncForUser(User $user, ?int $tenantId): void
    {
        $badges = Badge::query()->where('is_active', true)->where('issuance_type', '!=', Badge::ISSUE_MANUAL)
            ->where(function ($query) use ($tenantId): void {
                $query->where(fn ($global) => $global->where('scope', Badge::SCOPE_GLOBAL)->whereNull('tenant_id'));
                if ($tenantId !== null) {
                    $query->orWhere(fn ($tenant) => $tenant->where('scope', Badge::SCOPE_TENANT)->where('tenant_id', $tenantId));
                }
            })->get();
        foreach ($badges as $badge) {
            $this->syncManagedBadge($badge, $user);
        }
    }

    public function syncManagedBadge(Badge $badge, User $user): void
    {
        $eligible = $this->eligible($badge, $user);
        $active = $this->activeAssignmentQuery($badge, $user)->first();
        if ($eligible && ! $active) {
            UserBadge::create([
                'badge_id' => $badge->id, 'user_id' => $user->id, 'tenant_id' => $badge->tenant_id,
                'issued_by' => null, 'issued_at' => now(),
                'expires_at' => $badge->expires_after_days ? now()->addDays($badge->expires_after_days) : null,
                'metadata' => ['source' => $badge->issuance_type, 'criteria' => $badge->criteria],
            ]);
            return;
        }
        if (! $eligible && $active) {
            $active->update(['revoked_at' => now(), 'revoked_by' => null, 'revocation_reason' => 'Automatic badge criteria are no longer satisfied.']);
        }
    }

    public function awardManual(Badge $badge, User $user, ?User $issuer = null, ?string $expiresAt = null): UserBadge
    {
        $active = $this->activeAssignmentQuery($badge, $user)->first();
        if ($active) return $active;
        return UserBadge::create([
            'badge_id' => $badge->id, 'user_id' => $user->id, 'tenant_id' => $badge->tenant_id,
            'issued_by' => $issuer?->id, 'issued_at' => now(),
            'expires_at' => $expiresAt ? \Illuminate\Support\Carbon::parse($expiresAt) : ($badge->expires_after_days ? now()->addDays($badge->expires_after_days) : null),
            'metadata' => ['source' => 'manual'],
        ]);
    }

    public function revoke(UserBadge $assignment, ?User $actor, string $reason): void
    {
        if ($assignment->revoked_at !== null) return;
        $assignment->update(['revoked_at' => now(), 'revoked_by' => $actor?->id, 'revocation_reason' => $reason]);
    }

    public function activeForUser(User $user): Collection
    {
        $this->syncAllForUser($user);
        return UserBadge::query()->with(['badge.tenant', 'tenant', 'issuer'])->where('user_id', $user->id)
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('badge', fn ($badge) => $badge->where('is_active', true))->latest('issued_at')->get();
    }

    public function forDoor(User $user, int $tenantId): Collection
    {
        $this->syncForUser($user, $tenantId);
        return UserBadge::query()->with(['badge', 'tenant'])->where('user_id', $user->id)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('badge', function ($badge) use ($tenantId): void {
                $badge->where('is_active', true)->whereIn('visibility', ['public', 'members', 'tenant_members', 'event_attendees', 'staff'])
                    ->where(function ($scope) use ($tenantId): void {
                        $scope->where(fn ($global) => $global->where('scope', Badge::SCOPE_GLOBAL)->whereNull('tenant_id'))
                            ->orWhere(fn ($tenant) => $tenant->where('scope', Badge::SCOPE_TENANT)->where('tenant_id', $tenantId));
                    });
            })->get()->sortBy(fn (UserBadge $assignment) => $assignment->badge->scope === Badge::SCOPE_GLOBAL ? 0 : 1)->values();
    }

    private function activeAssignmentQuery(Badge $badge, User $user)
    {
        return UserBadge::query()->where('badge_id', $badge->id)->where('user_id', $user->id)->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    private function eligible(Badge $badge, User $user): bool
    {
        $criteria = $badge->criteria ?? [];
        if ($badge->issuance_type === Badge::ISSUE_MEMBERSHIP) {
            if (! $badge->tenant_id) return false;
            $membership = $user->tenants()->where('tenants.id', $badge->tenant_id)->wherePivot('status', 'active');
            if ($role = ($criteria['membership_role'] ?? null)) $membership->wherePivot('role', $role);
            return $membership->exists();
        }
        if ($badge->issuance_type === Badge::ISSUE_VERIFICATION) {
            $type = trim((string) ($criteria['verification_type'] ?? ''));
            if ($type === '') return false;
            $verification = DB::table('verifications')->where('user_id', $user->id)->where('type', $type)->where('status', 'verified')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            $badge->isGlobal() ? $verification->whereNull('tenant_id') : $verification->where('tenant_id', $badge->tenant_id);
            return $verification->exists();
        }
        if ($badge->issuance_type === Badge::ISSUE_AUTOMATIC) {
            $minimumEvents = (int) ($criteria['event_count'] ?? 0);
            if ($minimumEvents < 1) return false;
            $events = DB::table('event_checkins')->join('events', 'events.id', '=', 'event_checkins.event_id')->where('event_checkins.user_id', $user->id);
            if ($badge->tenant_id !== null) $events->where('events.tenant_id', $badge->tenant_id);
            return $events->distinct()->count('event_checkins.event_id') >= $minimumEvents;
        }
        return false;
    }
}
