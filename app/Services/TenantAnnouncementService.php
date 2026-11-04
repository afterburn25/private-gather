<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunityGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TenantAnnouncementService
{
    public function __construct(private readonly ProductCompletionService $products)
    {
    }

    public function publish(
        int $tenantId,
        int $announcementId,
        ?int $actorUserId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        bool $respectSchedule = false,
        string $trigger = 'operator',
    ): bool {
        return DB::transaction(function () use ($tenantId, $announcementId, $actorUserId, $ipAddress, $userAgent, $respectSchedule, $trigger): bool {
            $announcement = DB::table('tenant_announcements')
                ->where('id', $announcementId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $announcement) throw new InvalidArgumentException('Announcement does not belong to this organization.');
            if ($announcement->status === 'published') return false;
            // For status=scheduled, published_at temporarily stores the requested
            // publication time. When delivery occurs it is replaced by the actual
            // publication timestamp. This avoids a second scheduling column and
            // keeps browser-installer and Laravel-migration schemas identical.
            if ($respectSchedule && ($announcement->status !== 'scheduled' || ! $announcement->published_at || now()->lt($announcement->published_at))) return false;

            $recipients = $this->recipients($tenantId, $announcement);
            $tenantName = (string) (DB::table('tenants')->where('id', $tenantId)->value('name') ?: 'Private Gather');
            $bodySummary = Str::limit(preg_replace('/\s+/u', ' ', trim((string) $announcement->body)) ?: '', 220);
            $inboxLabel = $tenantName.': '.trim((string) $announcement->title).($bodySummary !== '' ? ' — '.$bodySummary : '');

            foreach ($recipients as $userId) {
                if (DB::table('tenant_announcement_deliveries')->where('announcement_id', $announcementId)->where('user_id', $userId)->exists()) continue;

                $this->products->notify((int) $userId, 'tenant.announcement', [
                    'announcement_id' => $announcementId,
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenantName,
                    'label' => $inboxLabel,
                    'title' => (string) $announcement->title,
                    'body' => (string) $announcement->body,
                    'priority' => (string) $announcement->priority,
                    'audience' => (string) $announcement->audience,
                    'event_id' => $announcement->event_id ? (int) $announcement->event_id : null,
                    'membership_level_id' => $announcement->membership_level_id ? (int) $announcement->membership_level_id : null,
                    'community_group_id' => $announcement->community_group_id ? (int) $announcement->community_group_id : null,
                    'expires_at' => $announcement->expires_at ?: null,
                ]);

                DB::table('tenant_announcement_deliveries')->insert([
                    'announcement_id' => $announcementId,
                    'user_id' => $userId,
                    'notification_id' => null,
                    'delivered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('tenant_announcements')->where('id', $announcementId)->update([
                'status' => 'published',
                'recipient_count' => $recipients->count(),
                'published_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $actorUserId,
                'action' => 'announcement.published',
                'subject_type' => 'tenant_announcement',
                'subject_id' => $announcementId,
                'before' => null,
                'after' => json_encode([
                    'audience' => $announcement->audience,
                    'event_id' => $announcement->event_id,
                    'membership_level_id' => $announcement->membership_level_id,
                    'community_group_id' => $announcement->community_group_id,
                    'recipient_count' => $recipients->count(),
                    'trigger' => $trigger,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                'created_at' => now(),
            ]);

            return true;
        });
    }

    private function recipients(int $tenantId, object $announcement): Collection
    {
        if ($announcement->audience === 'event_attendees') {
            $valid = $announcement->event_id && DB::table('events')->where('id', $announcement->event_id)->where('tenant_id', $tenantId)->exists();
            if (! $valid) throw new InvalidArgumentException('The announcement event no longer belongs to this organization.');
            return DB::table('event_rsvps')->where('event_id', $announcement->event_id)
                ->where(fn ($query) => $query->whereNotNull('approved_at')->orWhereIn('status', ['approved', 'confirmed', 'attending', 'going', 'checked_in', 'paid']))
                ->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
        }

        if ($announcement->audience === 'membership_level') {
            $valid = $announcement->membership_level_id && DB::table('tenant_membership_levels')->where('id', $announcement->membership_level_id)->where('tenant_id', $tenantId)->where('is_active', true)->exists();
            if (! $valid) throw new InvalidArgumentException('The selected membership level is no longer available to this organization.');
            return DB::table('tenant_membership_terms as terms')
                ->join('tenant_users as tenant_members', fn ($join) => $join->on('tenant_members.user_id', '=', 'terms.user_id')->where('tenant_members.tenant_id', '=', $tenantId)->where('tenant_members.status', '=', 'active'))
                ->where('terms.tenant_id', $tenantId)->where('terms.membership_level_id', $announcement->membership_level_id)->whereIn('terms.status', ['active', 'grace'])
                ->where(fn ($query) => $query->whereNull('terms.expires_at')->orWhere('terms.expires_at', '>', now()))
                ->pluck('terms.user_id')->map(fn ($id) => (int) $id)->unique()->values();
        }

        if ($announcement->audience === 'chapter_members') {
            $valid = $announcement->community_group_id && DB::table('community_groups')->where('id', $announcement->community_group_id)->where('tenant_id', $tenantId)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('status', 'active')->exists();
            if (! $valid) throw new InvalidArgumentException('The selected chapter is no longer active in this organization.');
            return DB::table('community_group_members as group_members')
                ->join('tenant_users as tenant_members', fn ($join) => $join->on('tenant_members.user_id', '=', 'group_members.user_id')->where('tenant_members.tenant_id', '=', $tenantId)->where('tenant_members.status', '=', 'active'))
                ->where('group_members.community_group_id', $announcement->community_group_id)->where('group_members.status', 'active')
                ->pluck('group_members.user_id')->map(fn ($id) => (int) $id)->unique()->values();
        }

        $query = DB::table('tenant_users')->where('tenant_id', $tenantId)->where('status', 'active');
        if ($announcement->audience === 'staff') $query->where('role', '!=', 'member');
        return $query->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
    }
}
