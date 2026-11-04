<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\CommunicationCampaignMail;
use App\Models\CommunityGroup;
use App\Models\TenantDomain;
use App\Support\TenantUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CommunicationsService
{
    public const MESSAGE_MARKETING = 'marketing';
    public const MESSAGE_OPERATIONAL = 'operational';

    public const SEGMENT_ALL_OPTED_IN = 'all_opted_in';
    public const SEGMENT_ALL_MEMBERS = 'all_members';
    public const SEGMENT_CHAPTER = 'chapter';
    public const SEGMENT_MEMBERSHIP_LEVEL = 'membership_level';
    public const SEGMENT_EVENT_ATTENDEES = 'event_attendees';
    public const SEGMENT_CRM_TAG = 'crm_tag';

    public function process(int $limit = 100): array
    {
        $remaining = max(1, min(1000, $limit));
        $counts = ['campaigns' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        $campaignIds = DB::table('marketing_campaigns')
            ->where(function ($query): void {
                $query->where('status', 'queued')
                    ->orWhere(function ($scheduled): void {
                        $scheduled->where('status', 'scheduled')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    })
                    ->orWhere('status', 'processing');
            })
            ->orderByRaw("CASE status WHEN 'processing' THEN 0 WHEN 'queued' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($campaignIds as $campaignId) {
            if ($remaining <= 0) break;

            $campaign = DB::transaction(function () use ($campaignId) {
                $campaign = DB::table('marketing_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
                if (! $campaign || ! in_array($campaign->status, ['queued', 'scheduled', 'processing'], true)) return null;
                if ($campaign->status === 'scheduled' && (! $campaign->scheduled_at || now()->lt($campaign->scheduled_at))) return null;

                if ($campaign->status !== 'processing') {
                    DB::table('marketing_campaigns')->where('id', $campaign->id)->update([
                        'status' => 'processing',
                        'processing_started_at' => $campaign->processing_started_at ?: now(),
                        'updated_at' => now(),
                    ]);
                    $campaign->status = 'processing';
                }
                return $campaign;
            });
            if (! $campaign) continue;

            $counts['campaigns']++;
            try {
                $this->ensureRecipients($campaign);

                $recipientIds = DB::table('marketing_campaign_recipients')
                    ->where('campaign_id', $campaign->id)
                    ->where('status', 'queued')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->pluck('id');

                foreach ($recipientIds as $recipientId) {
                    $result = $this->deliverRecipient((int) $recipientId);
                    if (array_key_exists($result, $counts)) $counts[$result]++;
                    $remaining--;
                    if ($remaining <= 0) break;
                }

                $this->refreshCampaignTotals((int) $campaign->id);
            } catch (\Throwable $exception) {
                report($exception);
                $this->markCampaignFailed((int) $campaign->id, $exception->getMessage());
                $counts['failed']++;
            }
        }

        return $counts;
    }

    public function ensureRecipients(object $campaign): int
    {
        if ((string) $campaign->channel !== 'email') {
            throw new InvalidArgumentException('Communications Center delivery currently supports email only. Other channels remain draft-only until a verified transport is connected.');
        }

        $existing = DB::table('marketing_campaign_recipients')->where('campaign_id', $campaign->id)->count();
        if ($existing > 0) return $existing;

        $segment = $campaign->segment_id
            ? DB::table('marketing_segments')->where('tenant_id', $campaign->tenant_id)->where('id', $campaign->segment_id)->where('is_active', true)->first()
            : null;
        $definition = $segment ? $this->decodeDefinition($segment->definition) : [
            'type' => $campaign->message_type === self::MESSAGE_OPERATIONAL ? self::SEGMENT_ALL_MEMBERS : self::SEGMENT_ALL_OPTED_IN,
        ];

        $recipients = $campaign->message_type === self::MESSAGE_OPERATIONAL
            ? $this->operationalRecipients((int) $campaign->tenant_id, $definition)
            : $this->marketingRecipients((int) $campaign->tenant_id, $definition);

        foreach ($recipients as $recipient) {
            $email = Str::lower(trim((string) $recipient['email']));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            DB::table('marketing_campaign_recipients')->insertOrIgnore([
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign->id,
                'marketing_contact_id' => $recipient['contact_id'] ?? null,
                'user_id' => $recipient['user_id'] ?? null,
                'channel' => 'email',
                'address_hash' => hash('sha256', $email),
                'address' => $email,
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $count = DB::table('marketing_campaign_recipients')->where('campaign_id', $campaign->id)->count();
        DB::table('marketing_campaigns')->where('id', $campaign->id)->update([
            'recipient_total' => $count,
            'stats' => json_encode(['eligible' => $count, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
        if ($count === 0) {
            DB::table('marketing_campaigns')->where('id', $campaign->id)->update(['status' => 'completed', 'sent_at' => now(), 'updated_at' => now()]);
        }

        return $count;
    }

    public function issueUnsubscribeToken(int $tenantId, int $contactId, string $channel = 'email'): string
    {
        if ($channel !== 'email') throw new InvalidArgumentException('Only email unsubscribe tokens are currently supported.');
        $contact = DB::table('marketing_contacts')->where('tenant_id', $tenantId)->where('id', $contactId)->first();
        if (! $contact) throw new InvalidArgumentException('Marketing contact was not found for this organization.');

        $raw = Str::random(64);
        DB::table('marketing_unsubscribe_tokens')->insert([
            'tenant_id' => $tenantId,
            'marketing_contact_id' => $contactId,
            'channel' => $channel,
            'token_digest' => hash('sha256', $raw),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $raw;
    }

    public function unsubscribe(string $token): string
    {
        if (preg_match('/^[A-Za-z0-9]{64}$/', $token) !== 1) throw new InvalidArgumentException('Invalid unsubscribe token.');

        return DB::transaction(function () use ($token): string {
            $record = DB::table('marketing_unsubscribe_tokens')
                ->where('token_digest', hash('sha256', $token))
                ->lockForUpdate()
                ->first();
            if (! $record || $record->used_at || !$record->expires_at || now()->gte($record->expires_at)) {
                throw new InvalidArgumentException('This unsubscribe link is invalid or expired.');
            }

            $contact = DB::table('marketing_contacts')
                ->where('tenant_id', $record->tenant_id)
                ->where('id', $record->marketing_contact_id)
                ->lockForUpdate()
                ->first();
            if (! $contact) throw new InvalidArgumentException('The marketing contact no longer exists.');

            DB::table('marketing_contacts')->where('id', $contact->id)->update([
                'email_opt_out_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('marketing_unsubscribe_tokens')
                ->where('marketing_contact_id', $contact->id)
                ->where('channel', 'email')
                ->whereNull('used_at')
                ->update(['used_at' => now(), 'updated_at' => now()]);

            return (string) (DB::table('tenants')->where('id', $record->tenant_id)->value('name') ?: 'this organization');
        });
    }

    private function deliverRecipient(int $recipientId): string
    {
        $recipient = DB::table('marketing_campaign_recipients')->where('id', $recipientId)->first();
        if (! $recipient || $recipient->status !== 'queued') return 'skipped';
        $campaign = DB::table('marketing_campaigns')->where('id', $recipient->campaign_id)->first();
        if (! $campaign || $campaign->status !== 'processing') return $this->markSkipped($recipientId, 'Campaign is no longer processing.');

        if ($campaign->message_type === self::MESSAGE_MARKETING) {
            $contact = $recipient->marketing_contact_id
                ? DB::table('marketing_contacts')->where('tenant_id', $campaign->tenant_id)->where('id', $recipient->marketing_contact_id)->first()
                : null;
            if (! $contact || $contact->status !== 'active' || !$contact->email_opt_in_at || $contact->email_opt_out_at) {
                return $this->markSkipped($recipientId, 'Marketing consent is not currently active.');
            }
            if (Str::lower(trim((string) $contact->email)) !== Str::lower(trim((string) $recipient->address))) {
                return $this->markSkipped($recipientId, 'Marketing contact address changed after campaign preparation.');
            }
        } else {
            $activeMember = $recipient->user_id && DB::table('tenant_users')
                ->join('users', 'users.id', '=', 'tenant_users.user_id')
                ->where('tenant_users.tenant_id', $campaign->tenant_id)
                ->where('tenant_users.user_id', $recipient->user_id)
                ->where('tenant_users.status', 'active')
                ->where('users.status', 'active')
                ->exists();
            if (! $activeMember) return $this->markSkipped($recipientId, 'Operational recipient is no longer an active organization member.');
        }

        $tenantName = (string) (DB::table('tenants')->where('id', $campaign->tenant_id)->value('name') ?: 'Private Gather');
        $subject = trim((string) $campaign->subject);
        if ($subject === '') return $this->markFailed($recipientId, 'Email subject is missing.');

        $unsubscribeUrl = null;
        if ($campaign->message_type === self::MESSAGE_MARKETING && $recipient->marketing_contact_id) {
            $host = DB::table('tenant_domains')
                ->where('tenant_id', $campaign->tenant_id)
                ->where('is_primary', true)
                ->where('status', TenantDomain::STATUS_ACTIVE)
                ->whereNotNull('verified_at')
                ->value('domain');
            if (!$host) return $this->markFailed($recipientId, 'A verified primary tenant domain is required for a trustworthy unsubscribe link.');
            $raw = $this->issueUnsubscribeToken((int) $campaign->tenant_id, (int) $recipient->marketing_contact_id);
            $unsubscribeUrl = TenantUrl::to((string) $host, '/communications/unsubscribe/'.$raw);
        }

        try {
            Mail::to((string) $recipient->address)->send(new CommunicationCampaignMail(
                tenantName: $tenantName,
                campaignSubject: $subject,
                campaignBody: (string) $campaign->body,
                messageType: (string) $campaign->message_type,
                unsubscribeUrl: $unsubscribeUrl,
            ));
            DB::table('marketing_campaign_recipients')->where('id', $recipientId)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failure_reason' => null,
                'updated_at' => now(),
            ]);
            if ($recipient->marketing_contact_id) {
                DB::table('marketing_contacts')->where('id', $recipient->marketing_contact_id)->update(['last_contacted_at' => now(), 'updated_at' => now()]);
            }
            return 'sent';
        } catch (\Throwable $exception) {
            report($exception);
            return $this->markFailed($recipientId, Str::limit($exception->getMessage(), 480));
        }
    }

    private function marketingRecipients(int $tenantId, array $definition): Collection
    {
        $query = DB::table('marketing_contacts')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->whereNotNull('email_opt_in_at')
            ->whereNull('email_opt_out_at');

        $type = (string) ($definition['type'] ?? self::SEGMENT_ALL_OPTED_IN);
        if ($type !== self::SEGMENT_ALL_OPTED_IN) {
            $userIds = $this->segmentUserIds($tenantId, $definition);
            if ($userIds->isEmpty()) return collect();
            $query->whereNotNull('user_id')->whereIn('user_id', $userIds);
        }

        return $query->orderBy('id')->get(['id', 'user_id', 'email'])->map(fn ($row) => [
            'contact_id' => (int) $row->id,
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'email' => (string) $row->email,
        ]);
    }

    private function operationalRecipients(int $tenantId, array $definition): Collection
    {
        $type = (string) ($definition['type'] ?? self::SEGMENT_ALL_MEMBERS);
        if ($type === self::SEGMENT_ALL_OPTED_IN) {
            throw new InvalidArgumentException('Operational messages cannot use the marketing-only all-opted-in segment.');
        }

        $query = DB::table('tenant_users')
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->where('tenant_users.status', 'active')
            ->where('users.status', 'active')
            ->whereNotNull('users.email');

        if ($type !== self::SEGMENT_ALL_MEMBERS) {
            $userIds = $this->segmentUserIds($tenantId, $definition);
            if ($userIds->isEmpty()) return collect();
            $query->whereIn('users.id', $userIds);
        }

        return $query->orderBy('users.id')->get(['users.id', 'users.email'])->map(fn ($row) => [
            'contact_id' => null,
            'user_id' => (int) $row->id,
            'email' => (string) $row->email,
        ]);
    }

    private function segmentUserIds(int $tenantId, array $definition): Collection
    {
        $type = (string) ($definition['type'] ?? '');
        $targetId = (int) ($definition['target_id'] ?? 0);

        if ($type === self::SEGMENT_ALL_MEMBERS) {
            return DB::table('tenant_users')->where('tenant_id', $tenantId)->where('status', 'active')->pluck('user_id');
        }

        if ($targetId <= 0) throw new InvalidArgumentException('This audience segment requires a valid target.');

        if ($type === self::SEGMENT_CHAPTER) {
            $valid = DB::table('community_groups')->where('tenant_id', $tenantId)->where('id', $targetId)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('status', 'active')->exists();
            if (!$valid) throw new InvalidArgumentException('The selected chapter is not active in this organization.');
            return DB::table('community_group_members as gm')
                ->join('tenant_users as tu', fn ($join) => $join->on('tu.user_id', '=', 'gm.user_id')->where('tu.tenant_id', '=', $tenantId)->where('tu.status', '=', 'active'))
                ->where('gm.community_group_id', $targetId)->where('gm.status', 'active')->pluck('gm.user_id')->unique()->values();
        }

        if ($type === self::SEGMENT_MEMBERSHIP_LEVEL) {
            $valid = DB::table('tenant_membership_levels')->where('tenant_id', $tenantId)->where('id', $targetId)->where('is_active', true)->exists();
            if (!$valid) throw new InvalidArgumentException('The selected membership level is not active in this organization.');
            return DB::table('tenant_membership_terms as terms')
                ->join('tenant_users as tu', fn ($join) => $join->on('tu.user_id', '=', 'terms.user_id')->where('tu.tenant_id', '=', $tenantId)->where('tu.status', '=', 'active'))
                ->where('terms.tenant_id', $tenantId)->where('terms.membership_level_id', $targetId)->whereIn('terms.status', ['active', 'grace'])
                ->where(fn ($query) => $query->whereNull('terms.expires_at')->orWhere('terms.expires_at', '>', now()))
                ->pluck('terms.user_id')->unique()->values();
        }

        if ($type === self::SEGMENT_EVENT_ATTENDEES) {
            $valid = DB::table('events')->where('tenant_id', $tenantId)->where('id', $targetId)->exists();
            if (!$valid) throw new InvalidArgumentException('The selected event does not belong to this organization.');
            return DB::table('event_rsvps as rsvps')
                ->join('tenant_users as tu', fn ($join) => $join->on('tu.user_id', '=', 'rsvps.user_id')->where('tu.tenant_id', '=', $tenantId)->where('tu.status', '=', 'active'))
                ->where('rsvps.event_id', $targetId)
                ->where(fn ($query) => $query->whereNotNull('rsvps.approved_at')->orWhereIn('rsvps.status', ['approved', 'confirmed', 'attending', 'going', 'checked_in', 'paid']))
                ->pluck('rsvps.user_id')->unique()->values();
        }

        if ($type === self::SEGMENT_CRM_TAG) {
            $valid = DB::table('tenant_member_tags')->where('tenant_id', $tenantId)->where('id', $targetId)->exists();
            if (!$valid) throw new InvalidArgumentException('The selected CRM tag does not belong to this organization.');
            return DB::table('tenant_member_tag_assignments as assignments')
                ->join('tenant_users as tu', fn ($join) => $join->on('tu.user_id', '=', 'assignments.user_id')->where('tu.tenant_id', '=', $tenantId)->where('tu.status', '=', 'active'))
                ->where('assignments.tenant_id', $tenantId)->where('assignments.tag_id', $targetId)
                ->pluck('assignments.user_id')->unique()->values();
        }

        throw new InvalidArgumentException('Unsupported communications audience segment.');
    }

    private function refreshCampaignTotals(int $campaignId): void
    {
        $counts = DB::table('marketing_campaign_recipients')->where('campaign_id', $campaignId)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped, SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened, SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked")
            ->first();
        $queued = DB::table('marketing_campaign_recipients')->where('campaign_id', $campaignId)->where('status', 'queued')->exists();

        DB::table('marketing_campaigns')->where('id', $campaignId)->update([
            'recipient_total' => (int) ($counts->total ?? 0),
            'sent_total' => (int) ($counts->sent ?? 0),
            'failed_total' => (int) ($counts->failed ?? 0),
            'skipped_total' => (int) ($counts->skipped ?? 0),
            'status' => $queued ? 'processing' : 'completed',
            'sent_at' => $queued ? null : now(),
            'stats' => json_encode([
                'eligible' => (int) ($counts->total ?? 0),
                'sent' => (int) ($counts->sent ?? 0),
                'failed' => (int) ($counts->failed ?? 0),
                'skipped' => (int) ($counts->skipped ?? 0),
                'delivered' => (int) ($counts->delivered ?? 0),
                'opened' => (int) ($counts->opened ?? 0),
                'clicked' => (int) ($counts->clicked ?? 0),
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    private function markCampaignFailed(int $campaignId, string $reason): void
    {
        $existing = DB::table('marketing_campaigns')->where('id', $campaignId)->value('stats');
        $stats = $this->decodeDefinition($existing);
        $stats['error'] = Str::limit($reason, 480);
        DB::table('marketing_campaigns')->where('id', $campaignId)->update([
            'status' => 'failed',
            'stats' => json_encode($stats, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    private function markSkipped(int $recipientId, string $reason): string
    {
        DB::table('marketing_campaign_recipients')->where('id', $recipientId)->update([
            'status' => 'skipped', 'failure_reason' => Str::limit($reason, 480), 'updated_at' => now(),
        ]);
        return 'skipped';
    }

    private function markFailed(int $recipientId, string $reason): string
    {
        DB::table('marketing_campaign_recipients')->where('id', $recipientId)->update([
            'status' => 'failed', 'failure_reason' => Str::limit($reason, 480), 'updated_at' => now(),
        ]);
        return 'failed';
    }

    private function decodeDefinition(mixed $definition): array
    {
        if (is_array($definition)) return $definition;
        $decoded = json_decode((string) $definition, true);
        return is_array($decoded) ? $decoded : [];
    }
}
