<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TenantOperationsOverviewService
{
    public function build(Tenant $tenant): array
    {
        $tenant->loadMissing(['domains', 'branding']);

        $primaryDomain = $tenant->domains->firstWhere('is_primary', true);
        $settings = (array) $tenant->settings;

        $checks = collect([
            $this->check(
                'domain',
                'Site address ready',
                $primaryDomain instanceof TenantDomain
                    && $primaryDomain->status === TenantDomain::STATUS_ACTIVE
                    && $primaryDomain->verified_at !== null,
                'Verify and activate a primary site address.',
                'tenant.domains.index'
            ),
            $this->check(
                'branding',
                'Brand identity configured',
                $tenant->branding !== null && (
                    filled($tenant->branding->logo_path)
                    || filled($tenant->branding->primary_color)
                    || filled($tenant->branding->accent_color)
                    || ! empty($tenant->branding->theme)
                ),
                'Add a logo, colors or a theme so the site has an intentional identity.',
                'tenant.branding.edit'
            ),
            $this->check(
                'website',
                'Homepage published',
                $tenant->pages()->where('status', 'published')->where('is_homepage', true)->exists(),
                'Publish the organization homepage before sending members to it.',
                'tenant.cms.pages'
            ),
            $this->check(
                'membership',
                'Membership structure ready',
                $tenant->membershipLevels()->where('is_active', true)->exists(),
                'Create at least one active membership level.',
                'tenant.membership-levels.index'
            ),
            $this->check(
                'privacy',
                'Privacy & access policy complete',
                $this->hasCompletePolicy($settings),
                'Review membership, event, directory, attendance and discovery rules.',
                'tenant.privacy-access.edit'
            ),
            $this->check(
                'event',
                'First event published',
                $tenant->events()->where('status', 'published')->exists(),
                'Publish an event so members have a live experience to discover.',
                'tenant.events.index'
            ),
        ]);

        $completed = $checks->where('complete', true)->count();
        $total = max(1, $checks->count());

        $activeMembers = DB::table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count();

        $upcomingEvents = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        $pendingApplications = $tenant->membershipApplications()
            ->where('status', 'pending')
            ->count();

        $scheduledAnnouncements = DB::table('tenant_announcements')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'scheduled')
            ->count();

        $recentAnnouncements = DB::table('tenant_announcements')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['published', 'scheduled'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'status', 'audience', 'published_at', 'created_at']);

        $recentActivity = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'user_id', 'action', 'subject_type', 'subject_id', 'created_at']);

        return [
            'readiness' => [
                'completed' => $completed,
                'total' => $total,
                'percent' => (int) round(($completed / $total) * 100),
                'checks' => $checks,
                'next_actions' => $checks->where('complete', false)->values(),
            ],
            'metrics' => [
                'active_members' => $activeMembers,
                'pending_applications' => $pendingApplications,
                'upcoming_events' => $upcomingEvents->count(),
                'scheduled_announcements' => $scheduledAnnouncements,
            ],
            'upcoming_events' => $upcomingEvents,
            'recent_announcements' => $recentAnnouncements,
            'recent_activity' => $recentActivity,
            'domain_warning' => $this->domainWarning($primaryDomain),
        ];
    }

    private function check(string $key, string $label, bool $complete, string $action, string $route): array
    {
        return compact('key', 'label', 'complete', 'action', 'route');
    }

    private function hasCompletePolicy(array $settings): bool
    {
        foreach ([
            'membership_access',
            'event_access_default',
            'venue_address_visibility',
            'network_visibility',
            'member_directory_visibility',
            'event_attendance_visibility',
        ] as $key) {
            if (! array_key_exists($key, $settings) || blank($settings[$key])) {
                return false;
            }
        }

        return true;
    }

    private function domainWarning(?TenantDomain $domain): ?string
    {
        if (! $domain) {
            return 'No primary domain is configured.';
        }
        if ($domain->status !== TenantDomain::STATUS_ACTIVE || $domain->verified_at === null) {
            return 'The primary domain still needs verification or activation.';
        }
        if (in_array($domain->ssl_status, ['failed', 'error'], true)) {
            return 'The primary domain is active, but SSL needs attention.';
        }
        if (filled($domain->last_error)) {
            return 'Domain health reported: '.$domain->last_error;
        }

        return null;
    }
}
