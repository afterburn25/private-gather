<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventRsvp;
use App\Models\Ticket;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckinController extends Controller
{
    public function index(TenantContext $ctx, Event $event)
    {
        $tenant = $ctx->requireTenant();
        abort_unless($event->tenant_id === $tenant->id, 404);
        $checkins = EventCheckin::where('event_id', $event->id)->latest('checked_in_at')->paginate(100);

        return view('tenant.manage.checkin.index', compact('event', 'checkins'));
    }

    public function manual(Request $r, TenantContext $ctx, Event $event)
    {
        $tenant = $ctx->requireTenant();
        abort_unless($event->tenant_id === $tenant->id, 404);
        abort_unless($event->status === 'published', 422, 'Only published events can accept check-ins.');

        $d = $r->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'guest_count' => 'required|integer|min:1|max:20',
        ]);

        DB::transaction(function () use ($d, $event, $r): void {
            // Event-row locking serializes manual and QR check-ins so the same
            // attendee entitlement cannot be consumed twice concurrently.
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedEvent->status === 'published', 422, 'Only published events can accept check-ins.');

            $userId = isset($d['user_id']) ? (int) $d['user_id'] : null;
            if ($userId !== null) {
                $rsvpGuests = (int) (EventRsvp::where('event_id', $lockedEvent->id)
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->value('guest_count') ?? 0);

                $ticketGuests = Ticket::where('user_id', $userId)
                    ->whereIn('status', ['valid', 'used'])
                    ->whereHas('orderItem.order', fn ($query) => $query->where('event_id', $lockedEvent->id))
                    ->count();

                // RSVP guest allowance and issued tickets may describe the same
                // party, so use the greater entitlement rather than adding them.
                $entitlement = max($rsvpGuests, $ticketGuests);
                abort_if($entitlement < 1, 422, 'This member is not an approved attendee or ticket holder for this event.');

                $alreadyCheckedIn = (int) EventCheckin::where('event_id', $lockedEvent->id)
                    ->where('user_id', $userId)
                    ->sum('guest_count');

                abort_if(
                    $alreadyCheckedIn + (int) $d['guest_count'] > $entitlement,
                    422,
                    'This check-in would exceed the member’s approved guest/ticket allowance.'
                );
            }

            EventCheckin::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $userId,
                'checked_in_by' => $r->user()->id,
                'method' => 'manual',
                'guest_count' => $d['guest_count'],
                'checked_in_at' => now(),
            ]);
        });

        return back()->with('status', 'Guest checked in.');
    }

    public function ticket(Request $r, TenantContext $ctx, Event $event)
    {
        $tenant = $ctx->requireTenant();
        abort_unless($event->tenant_id === $tenant->id, 404);
        abort_unless($event->status === 'published', 422, 'Only published events can accept check-ins.');
        $d = $r->validate(['qr_token' => 'required|string|max:100']);

        DB::transaction(function () use ($d, $event, $r): void {
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedEvent->status === 'published', 422, 'Only published events can accept check-ins.');

            $ticket = Ticket::where('qr_token', $d['qr_token'])->lockForUpdate()->firstOrFail();
            $ticket->load('orderItem.order');
            abort_unless(optional(optional($ticket->orderItem)->order)->event_id === $lockedEvent->id, 422, 'Ticket is for another event.');
            abort_if($ticket->status !== 'valid' || $ticket->checked_in_at, 422, 'Ticket is not valid for check-in.');

            $ticket->update(['checked_in_at' => now(), 'status' => 'used']);
            EventCheckin::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $ticket->user_id,
                'ticket_id' => $ticket->id,
                'checked_in_by' => $r->user()->id,
                'method' => 'qr',
                'guest_count' => 1,
                'checked_in_at' => now(),
            ]);
        });

        return back()->with('status', 'Ticket accepted.');
    }
}
