<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Support\TenantMembership;
use Illuminate\Support\Facades\DB;

final class EventVenueAccessService
{
    public function canSeeExactAddress(Event $event, ?User $viewer): bool
    {
        return match ((string) $event->exact_address_visibility) {
            'public' => true,
            'members' => $viewer !== null
                && TenantMembership::hasActiveMembership($viewer, (int) $event->tenant_id),
            'approved_attendees' => $viewer !== null
                && $this->hasApprovedAttendance($event, (int) $viewer->id),
            'approved_or_ticketed' => $viewer !== null
                && ($this->hasApprovedAttendance($event, (int) $viewer->id)
                    || $this->hasValidOrUsedTicket($event, (int) $viewer->id)),
            default => false,
        };
    }

    public function defaultVisibility(array $tenantSettings): string
    {
        return match ((string) ($tenantSettings['venue_address_visibility'] ?? 'approved_or_ticketed')) {
            'public' => 'public',
            'members' => 'members',
            'approved_or_ticketed' => 'approved_or_ticketed',
            default => 'organizer_only',
        };
    }

    private function hasApprovedAttendance(Event $event, int $userId): bool
    {
        return DB::table('event_rsvps')
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('status', 'approved')->orWhereNotNull('approved_at');
            })
            ->exists();
    }

    private function hasValidOrUsedTicket(Event $event, int $userId): bool
    {
        return DB::table('tickets')
            ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('tickets.user_id', $userId)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->where('orders.event_id', $event->id)
            ->where('orders.tenant_id', $event->tenant_id)
            ->whereIn('orders.status', ['paid', 'completed'])
            ->exists();
    }
}
