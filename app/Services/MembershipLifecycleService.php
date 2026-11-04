<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TenantMembershipInvitation;
use App\Models\TenantMembershipTerm;
use App\Notifications\MembershipRenewalReminderNotification;
use Illuminate\Support\Facades\DB;

final class MembershipLifecycleService
{
    public function __construct(private MembershipLevelService $levels) {}

    public function process(int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));
        $counts = [
            'reminders' => 0,
            'renewed' => 0,
            'grace' => 0,
            'expired' => 0,
            'cancelled' => 0,
            'invitations_expired' => 0,
        ];

        $invitationIds = TenantMembershipInvitation::query()
            ->where('status', TenantMembershipInvitation::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        if ($invitationIds->isNotEmpty()) {
            $counts['invitations_expired'] = TenantMembershipInvitation::query()
                ->whereIn('id', $invitationIds)
                ->where('status', TenantMembershipInvitation::STATUS_PENDING)
                ->update(['status' => TenantMembershipInvitation::STATUS_EXPIRED, 'updated_at' => now()]);
        }

        $reminderIds = TenantMembershipTerm::query()
            ->whereIn('status', [TenantMembershipTerm::STATUS_ACTIVE, TenantMembershipTerm::STATUS_GRACE])
            ->whereNull('renewal_reminder_sent_at')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '>', now())
            ->where('current_period_ends_at', '<=', now()->addDays(7))
            ->orderBy('current_period_ends_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($reminderIds as $id) {
            if ($this->sendReminderOnce((int) $id)) {
                $counts['reminders']++;
            }
        }

        $dueIds = TenantMembershipTerm::query()
            ->whereIn('status', [TenantMembershipTerm::STATUS_ACTIVE, TenantMembershipTerm::STATUS_GRACE])
            ->where(function ($query): void {
                $query->where(function ($period): void {
                    $period->whereNotNull('current_period_ends_at')->where('current_period_ends_at', '<=', now());
                })->orWhere(function ($expiry): void {
                    $expiry->whereNotNull('expires_at')->where('expires_at', '<=', now());
                });
            })
            ->orderByRaw('COALESCE(current_period_ends_at, expires_at)')
            ->limit($limit)
            ->pluck('id');

        foreach ($dueIds as $id) {
            $term = TenantMembershipTerm::query()->find($id);
            if (! $term) continue;
            $result = $this->levels->processLifecycle($term);
            if (array_key_exists($result, $counts)) {
                $counts[$result]++;
            }
        }

        return $counts;
    }

    private function sendReminderOnce(int $termId): bool
    {
        return DB::transaction(function () use ($termId): bool {
            $term = TenantMembershipTerm::query()->lockForUpdate()->find($termId);
            if (! $term || $term->renewal_reminder_sent_at !== null || ! $term->current_period_ends_at) {
                return false;
            }
            if ($term->current_period_ends_at->lte(now()) || $term->current_period_ends_at->gt(now()->addDays(7))) {
                return false;
            }

            $term->loadMissing(['user', 'tenant', 'level']);
            if (! $term->user) return false;

            $notification = new MembershipRenewalReminderNotification($term);
            app(ProductCompletionService::class)->notify(
                (int) $term->user->id,
                'membership.renewal_reminder',
                $notification->toDatabase($term->user),
            );
            $term->update(['renewal_reminder_sent_at' => now()]);
            return true;
        });
    }
}
