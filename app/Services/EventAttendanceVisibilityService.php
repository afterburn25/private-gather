<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Support\TenantMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventAttendanceVisibilityService
{
    private const ATTENDING_STATUSES = ['approved', 'confirmed', 'attending', 'going', 'checked_in', 'paid'];

    public function visibleAttendees(Event $event, ?User $viewer): Collection
    {
        if (! $viewer) {
            return collect();
        }

        $tenant = $event->tenant;
        $mode = (string) (($tenant->settings ?? [])['event_attendance_visibility'] ?? 'private');
        $viewerId = (int) $viewer->id;
        $viewerIsMember = TenantMembership::hasActiveMembership($viewer, (int) $event->tenant_id);
        $viewerIsAttendee = $this->isAttending($event, $viewerId);

        if ($mode === 'private') {
            return collect();
        }

        if ($mode === 'members' && ! $viewerIsMember) {
            return collect();
        }

        if ($mode === 'attendees' && ! $viewerIsAttendee) {
            return collect();
        }

        if (! in_array($mode, ['members', 'attendees'], true)) {
            return collect();
        }

        $blockedIds = DB::table('user_blocks')
            ->where('user_id', $viewerId)
            ->pluck('blocked_user_id')
            ->merge(DB::table('user_blocks')->where('blocked_user_id', $viewerId)->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $connectionIds = DB::table('user_connections')
            ->where('status', 'accepted')
            ->where(function ($query) use ($viewerId): void {
                $query->where('requester_id', $viewerId)->orWhere('addressee_id', $viewerId);
            })
            ->get(['requester_id', 'addressee_id'])
            ->map(fn ($row) => (int) $row->requester_id === $viewerId ? (int) $row->addressee_id : (int) $row->requester_id)
            ->unique()
            ->values();

        return DB::table('event_rsvps')
            ->join('users', 'users.id', '=', 'event_rsvps.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->leftJoin('user_privacy_settings', 'user_privacy_settings.user_id', '=', 'users.id')
            ->where('event_rsvps.event_id', $event->id)
            ->where(function ($query): void {
                $query->whereIn('event_rsvps.status', self::ATTENDING_STATUSES)
                    ->orWhereNotNull('event_rsvps.approved_at');
            })
            ->when($blockedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('users.id', $blockedIds))
            ->where(function ($query) use ($viewerId, $viewerIsMember, $connectionIds): void {
                $query->where('users.id', $viewerId)
                    ->orWhere(function ($privacy) use ($viewerIsMember, $connectionIds): void {
                        $privacy->where(function ($settings): void {
                            $settings->whereNull('user_privacy_settings.attendance_visibility')
                                ->orWhere('user_privacy_settings.attendance_visibility', 'members');
                        });

                        if (! $viewerIsMember) {
                            $privacy->whereRaw('1 = 0');
                        }
                    });

                if ($connectionIds->isNotEmpty()) {
                    $query->orWhere(function ($connections) use ($connectionIds): void {
                        $connections->where('user_privacy_settings.attendance_visibility', 'connections')
                            ->whereIn('users.id', $connectionIds);
                    });
                }
            })
            ->where(function ($query): void {
                // Missing privacy rows use the migration's conservative attendance default.
                $query->whereNotNull('user_privacy_settings.attendance_visibility')
                    ->orWhereRaw('1 = 0');
            })
            ->orderByRaw('COALESCE(users.display_name, users.name)')
            ->get([
                'users.id',
                'users.display_name',
                'users.name',
                'profiles.profile_type',
            ]);
    }

    private function isAttending(Event $event, int $userId): bool
    {
        return DB::table('event_rsvps')
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->whereIn('status', self::ATTENDING_STATUSES)->orWhereNotNull('approved_at');
            })
            ->exists();
    }
}
