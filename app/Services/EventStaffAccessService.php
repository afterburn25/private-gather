<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EventStaffAccessService
{
    public const CAP_CHECKIN = 'checkin';
    public const CAP_ATTENDEES = 'attendees';
    public const CAP_COMMUNICATIONS = 'communications';

    public function can(?User $user, Event $event, string $capability): bool
    {
        if (! $user) return false;
        if ($user->is_platform_admin) return true;

        $pivot = $user->tenants()->whereKey($event->tenant_id)->first()?->pivot;
        if ($pivot && $pivot->status === 'active' && in_array($pivot->role, ['owner', 'admin', 'manager'], true)) {
            return true;
        }

        if (! $pivot || $pivot->status !== 'active') return false;

        $assignment = DB::table('event_staff_assignments')
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        if (! $assignment) return false;

        $capabilities = is_array($assignment->capabilities ?? null)
            ? $assignment->capabilities
            : json_decode((string) ($assignment->capabilities ?? '[]'), true);

        return in_array($capability, is_array($capabilities) ? $capabilities : [], true);
    }

    public function assertCan(?User $user, Event $event, string $capability): void
    {
        abort_unless($this->can($user, $event, $capability), 403);
    }

    public function assignableUsers(Event $event)
    {
        return DB::table('tenant_users')
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->where('tenant_users.tenant_id', $event->tenant_id)
            ->where('tenant_users.status', 'active')
            ->where('users.status', 'active')
            ->orderBy('users.display_name')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.display_name', 'users.email', 'tenant_users.role']);
    }
}
