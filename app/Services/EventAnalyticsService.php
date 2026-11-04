<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventRsvp;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EventAnalyticsService
{
    public function summary(int $tenantId, Carbon $from): array
    {
        $views = AnalyticsEvent::where('tenant_id', $tenantId)->where('event_name', 'event.view')->where('occurred_at', '>=', $from)->count();
        $rsvps = EventRsvp::whereHas('event', fn ($query) => $query->where('tenant_id', $tenantId))->where('created_at', '>=', $from);
        $checkins = EventCheckin::whereHas('event', fn ($query) => $query->where('tenant_id', $tenantId))->where('checked_in_at', '>=', $from);
        $orders = Order::where('tenant_id', $tenantId)->where('created_at', '>=', $from);
        $paid = (clone $orders)->whereIn('status', ['paid', 'completed']);

        $rsvpTotal = (clone $rsvps)->count();
        $approvedRsvps = (clone $rsvps)->where('status', 'approved');
        $approvedParty = (int) (clone $approvedRsvps)->sum('guest_count');
        $checkedInParty = (int) (clone $checkins)->sum('guest_count');
        $paidOrders = (clone $paid)->count();
        $gross = (int) (clone $paid)->sum('subtotal_cents');
        $discounts = (int) (clone $paid)->sum('discount_cents');
        $collected = (int) (clone $paid)->sum('total_cents');

        return [
            'event_views' => $views,
            'rsvps' => $rsvpTotal,
            'approved_rsvps' => (clone $approvedRsvps)->count(),
            'pending_rsvps' => (clone $rsvps)->where('status', 'pending')->count(),
            'cancelled_rsvps' => (clone $rsvps)->where('status', 'cancelled')->count(),
            'approved_party' => $approvedParty,
            'checked_in_party' => $checkedInParty,
            'no_show_party' => max(0, $approvedParty - $checkedInParty),
            'checkin_rate' => $approvedParty > 0 ? round(($checkedInParty / $approvedParty) * 100, 1) : 0.0,
            'rsvp_conversion' => $views > 0 ? round(($rsvpTotal / $views) * 100, 1) : 0.0,
            'orders' => (clone $orders)->count(),
            'paid_orders' => $paidOrders,
            'order_conversion' => (clone $orders)->count() > 0 ? round(($paidOrders / (clone $orders)->count()) * 100, 1) : 0.0,
            'gross_cents' => $gross,
            'discount_cents' => $discounts,
            'collected_cents' => $collected,
            'comp_orders' => (clone $paid)->where('total_cents', 0)->where('discount_cents', '>', 0)->count(),
        ];
    }

    public function events(int $tenantId, Carbon $from, int $limit = 30): Collection
    {
        return Event::where('tenant_id', $tenantId)
            ->latest('starts_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(function (Event $event) use ($from): array {
                $rsvps = $event->rsvps()->where('created_at', '>=', $from);
                $approvedParty = (int) (clone $rsvps)->where('status', 'approved')->sum('guest_count');
                $checkedInParty = (int) $event->checkins()->where('checked_in_at', '>=', $from)->sum('guest_count');
                $orders = $event->orders()->where('created_at', '>=', $from);
                $paid = (clone $orders)->whereIn('status', ['paid', 'completed']);
                $views = AnalyticsEvent::where('tenant_id', $event->tenant_id)
                    ->where('event_id', $event->id)
                    ->where('event_name', 'event.view')
                    ->where('occurred_at', '>=', $from)
                    ->count();
                $rsvpCount = (clone $rsvps)->count();

                return [
                    'event' => $event,
                    'views' => $views,
                    'rsvps' => $rsvpCount,
                    'approved_party' => $approvedParty,
                    'checked_in_party' => $checkedInParty,
                    'no_show_party' => max(0, $approvedParty - $checkedInParty),
                    'checkin_rate' => $approvedParty > 0 ? round(($checkedInParty / $approvedParty) * 100, 1) : 0.0,
                    'rsvp_conversion' => $views > 0 ? round(($rsvpCount / $views) * 100, 1) : 0.0,
                    'paid_orders' => (clone $paid)->count(),
                    'discount_cents' => (int) (clone $paid)->sum('discount_cents'),
                    'collected_cents' => (int) (clone $paid)->sum('total_cents'),
                ];
            });
    }
}
