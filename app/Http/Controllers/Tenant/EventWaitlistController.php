<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use App\Support\Audit;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class EventWaitlistController extends Controller
{
    public function index(TenantContext $context, Event $event): View
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);

        $approvedSeats = (int) EventRsvp::query()
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->sum('guest_count');

        $remainingSeats = $event->capacity === null
            ? null
            : max(0, (int) $event->capacity - $approvedSeats);

        $waitlist = DB::table('event_waitlist')
            ->join('users', 'users.id', '=', 'event_waitlist.user_id')
            ->where('event_waitlist.event_id', $event->id)
            ->orderBy('event_waitlist.position')
            ->orderBy('event_waitlist.id')
            ->select([
                'event_waitlist.id',
                'event_waitlist.user_id',
                'event_waitlist.guest_count',
                'event_waitlist.position',
                'event_waitlist.created_at',
                'users.name',
                'users.display_name',
                'users.email',
                'users.status as user_status',
            ])
            ->paginate(100);

        return view('tenant.manage.events.waitlist', [
            'event' => $event,
            'waitlist' => $waitlist,
            'approvedSeats' => $approvedSeats,
            'remainingSeats' => $remainingSeats,
        ]);
    }

    public function promote(Request $request, TenantContext $context, Event $event, int $waitlist)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);

        $promotion = DB::transaction(function () use ($event, $waitlist): array {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedEvent->status === 'published', 422, 'Only published events can promote waitlisted attendees.');

            $entry = DB::table('event_waitlist')
                ->where('event_id', $lockedEvent->id)
                ->where('id', $waitlist)
                ->lockForUpdate()
                ->first();
            abort_unless($entry, 404);

            $user = User::query()->whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'active', 422, 'This waitlisted account is not active.');

            if ($lockedEvent->visibility === 'members') {
                abort_unless(
                    TenantMembership::hasActiveMembership($user, (int) $lockedEvent->tenant_id),
                    422,
                    'This waitlisted user is no longer an active organization member.'
                );
            }

            $existing = EventRsvp::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'approved') {
                DB::table('event_waitlist')->where('id', $entry->id)->delete();

                return [
                    'waitlist_id' => (int) $entry->id,
                    'user_id' => (int) $user->id,
                    'guest_count' => (int) $existing->guest_count,
                    'already_approved' => true,
                ];
            }

            if ($lockedEvent->capacity !== null) {
                $approvedSeats = (int) EventRsvp::query()
                    ->where('event_id', $lockedEvent->id)
                    ->where('status', 'approved')
                    ->sum('guest_count');

                abort_if(
                    $approvedSeats + (int) $entry->guest_count > (int) $lockedEvent->capacity,
                    422,
                    'Not enough event capacity is available for this waitlisted party.'
                );
            }

            if ($existing) {
                $existing->update([
                    'status' => 'approved',
                    'guest_count' => (int) $entry->guest_count,
                    'approved_at' => now(),
                ]);
            } else {
                EventRsvp::query()->create([
                    'event_id' => $lockedEvent->id,
                    'user_id' => $user->id,
                    'status' => 'approved',
                    'guest_count' => (int) $entry->guest_count,
                    'answers' => [],
                    'approved_at' => now(),
                ]);
            }

            DB::table('event_waitlist')->where('id', $entry->id)->delete();

            return [
                'waitlist_id' => (int) $entry->id,
                'user_id' => (int) $user->id,
                'guest_count' => (int) $entry->guest_count,
                'already_approved' => false,
            ];
        });

        Audit::write(
            'event.waitlist.promoted',
            $event,
            after: $promotion,
            tenantId: $context->id(),
            request: $request,
        );

        return back()->with(
            'status',
            $promotion['already_approved']
                ? 'The member was already approved; the stale waitlist entry was removed.'
                : 'Waitlisted member promoted to an approved RSVP.'
        );
    }
}
