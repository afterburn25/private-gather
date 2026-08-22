<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMembershipLevel;
use App\Models\TenantMembershipTerm;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipLevelService
{
    public function __construct(private BadgeService $badges) {}

    public function ensureDefaultLevel(Tenant $tenant): TenantMembershipLevel
    {
        $default = TenantMembershipLevel::where('tenant_id', $tenant->id)->where('is_default', true)->where('is_active', true)->orderBy('sort_order')->first();
        if ($default) return $default;

        $existing = TenantMembershipLevel::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->first();
        if ($existing) {
            $existing->update(['is_default' => true]);
            return $existing;
        }

        return TenantMembershipLevel::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard Member',
            'slug' => 'standard-member',
            'description' => 'Default approved lifestyle club or organization membership.',
            'price_cents' => 0,
            'currency' => 'USD',
            'billing_interval' => TenantMembershipLevel::INTERVAL_NONE,
            'duration_days' => null,
            'profile_eligibility' => 'any',
            'guest_limit' => 0,
            'event_discount_percent' => 0,
            'requires_approval' => true,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 10,
            'benefits' => ['Approved member access'],
        ]);
    }

    public function applyLevel(Tenant $tenant, User $user, TenantMembershipLevel $level, ?User $actor = null, string $source = 'staff', bool $autoRenew = false, ?string $notes = null): TenantMembershipTerm
    {
        abort_unless((int) $level->tenant_id === (int) $tenant->id, 404);
        abort_unless($level->is_active, 422, 'That membership level is inactive.');
        $this->assertProfileEligible($user, $level);

        $term = DB::transaction(function () use ($tenant, $user, $level, $actor, $source, $autoRenew, $notes): TenantMembershipTerm {
            $existing = $tenant->users()->whereKey($user->id)->first();
            if (! $existing) {
                $tenant->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
            } elseif ($existing->pivot->role === 'member' && $existing->pivot->status !== 'active') {
                $tenant->users()->updateExistingPivot($user->id, ['status' => 'active']);
            }

            $start = now();
            $end = $this->periodEnd($level, $start);
            $current = TenantMembershipTerm::where('tenant_id', $tenant->id)->where('user_id', $user->id)->first();
            $before = $current?->toArray();

            $term = TenantMembershipTerm::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'membership_level_id' => $level->id,
                    'status' => TenantMembershipTerm::STATUS_ACTIVE,
                    'starts_at' => $current?->starts_at ?? $start,
                    'current_period_starts_at' => $start,
                    'current_period_ends_at' => $end,
                    'renews_at' => $autoRenew ? $end : null,
                    'expires_at' => $end,
                    'auto_renew' => $autoRenew,
                    'cancel_at_period_end' => false,
                    'source' => $source,
                    'provider' => null,
                    'provider_reference' => null,
                    'notes' => $notes,
                    'created_by' => $actor?->id,
                ]
            );

            Audit::write('tenant.membership.level.assigned', $term, before: $before, after: $term->fresh()->toArray(), tenantId: $tenant->id);
            return $term;
        });

        $this->badges->syncForUser($user, $tenant->id);
        return $term->fresh(['level', 'user']);
    }

    public function renew(TenantMembershipTerm $term, ?User $actor = null): TenantMembershipTerm
    {
        $term->loadMissing(['level', 'tenant', 'user']);
        $before = $term->toArray();
        $base = $term->current_period_ends_at && $term->current_period_ends_at->isFuture() ? $term->current_period_ends_at->copy() : now();
        $end = $this->periodEnd($term->level, $base);
        $term->update(['status' => TenantMembershipTerm::STATUS_ACTIVE, 'current_period_starts_at' => $base, 'current_period_ends_at' => $end, 'renews_at' => $term->auto_renew ? $end : null, 'expires_at' => $end, 'cancel_at_period_end' => false]);

        $membership = $term->tenant->users()->whereKey($term->user_id)->first();
        if ($membership && $membership->pivot->role === 'member' && $membership->pivot->status !== 'active') $term->tenant->users()->updateExistingPivot($term->user_id, ['status' => 'active']);

        Audit::write('tenant.membership.renewed', $term, before: $before, after: $term->fresh()->toArray(), tenantId: $term->tenant_id);
        $this->badges->syncForUser($term->user, $term->tenant_id);
        return $term->fresh(['level', 'user']);
    }

    public function cancel(TenantMembershipTerm $term, ?User $actor, bool $atPeriodEnd, ?string $reason = null): TenantMembershipTerm
    {
        $term->loadMissing(['tenant', 'user', 'level']);
        $before = $term->toArray();
        if ($atPeriodEnd && $term->expires_at && $term->expires_at->isFuture()) {
            $term->update(['cancel_at_period_end' => true, 'auto_renew' => false, 'renews_at' => null, 'notes' => $reason ?: $term->notes]);
        } else {
            $term->update(['status' => TenantMembershipTerm::STATUS_CANCELLED, 'expires_at' => now(), 'current_period_ends_at' => now(), 'cancel_at_period_end' => false, 'auto_renew' => false, 'renews_at' => null, 'notes' => $reason ?: $term->notes]);
        }
        Audit::write('tenant.membership.cancelled', $term, before: $before, after: $term->fresh()->toArray(), tenantId: $term->tenant_id);
        $this->badges->syncForUser($term->user, $term->tenant_id);
        return $term->fresh(['level', 'user']);
    }

    public function markExpiredIfDue(TenantMembershipTerm $term): TenantMembershipTerm
    {
        if ($term->expires_at && $term->expires_at->lte(now()) && in_array($term->status, [TenantMembershipTerm::STATUS_ACTIVE, TenantMembershipTerm::STATUS_GRACE], true)) {
            $term->update(['status' => TenantMembershipTerm::STATUS_EXPIRED, 'renews_at' => null]);
            $term->loadMissing('user');
            $this->badges->syncForUser($term->user, $term->tenant_id);
        }
        return $term;
    }

    private function periodEnd(TenantMembershipLevel $level, Carbon $from): ?Carbon
    {
        return match ($level->billing_interval) {
            TenantMembershipLevel::INTERVAL_MONTHLY => $from->copy()->addMonthNoOverflow(),
            TenantMembershipLevel::INTERVAL_QUARTERLY => $from->copy()->addMonthsNoOverflow(3),
            TenantMembershipLevel::INTERVAL_ANNUAL => $from->copy()->addYearNoOverflow(),
            TenantMembershipLevel::INTERVAL_CUSTOM => $from->copy()->addDays(max(1, (int) $level->duration_days)),
            TenantMembershipLevel::INTERVAL_NONE, TenantMembershipLevel::INTERVAL_LIFETIME => null,
            default => null,
        };
    }

    private function assertProfileEligible(User $user, TenantMembershipLevel $level): void
    {
        if ($level->profile_eligibility === 'any') return;
        $profileType = $user->profile?->profile_type;
        if ($profileType !== $level->profile_eligibility) {
            throw ValidationException::withMessages(['membership_level_id' => $level->profile_eligibility === 'couple' ? 'This membership level is reserved for couple profiles.' : 'This membership level is reserved for individual profiles.']);
        }
    }
}
