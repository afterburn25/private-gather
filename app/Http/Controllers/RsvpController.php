<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RsvpController extends Controller
{
    public function store(Request $request, TenantContext $context, Event $event)
    {
        abort_unless($event->status === 'published', 404);
        $tenantId = $context->check() ? $context->id() : null;
        if ($tenantId !== null) {
            abort_unless((int) $tenantId === (int) $event->tenant_id, 404);
        } else {
            abort_unless($event->visibility === 'public', 404);
        }

        if ($event->visibility === 'members') {
            abort_unless(
                TenantMembership::canAccessMembersContent($request->user(), (int) $event->tenant_id),
                403
            );
        }

        abort_if(in_array($event->visibility, ['private', 'invite_only'], true), 403, 'This event requires an organizer invitation.');
        abort_unless($request->user()->isAdult(), 403, 'An adult account is required.');

        if ($event->registration_opens_at) {
            abort_if(now()->lt($event->registration_opens_at), 422, 'Registration has not opened.');
        }
        if ($event->registration_closes_at) {
            abort_if(now()->gt($event->registration_closes_at), 422, 'Registration is closed.');
        }
        if ($event->requires_verified_profile) {
            abort_unless(
                $request->user()->hasVerifiedEmail()
                || DB::table('verifications')->where('user_id', $request->user()->id)->where('status', 'verified')->exists(),
                403,
                'A verified profile is required.'
            );
        }

        $data = $request->validate([
            'guest_count' => 'required|integer|min:1|max:10',
            'answers' => 'nullable|array',
        ]);
        $user = $request->user();

        return DB::transaction(function () use ($event, $user, $data, $tenantId) {
            // Serialize capacity decisions for this event. Without an event-row
            // lock, simultaneous instant RSVPs can both see the same free seats.
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedEvent->status === 'published', 404);
            if ($tenantId !== null) {
                abort_unless((int) $lockedEvent->tenant_id === (int) $tenantId, 404);
            }
            if ($lockedEvent->visibility === 'members') {
                abort_unless(
                    TenantMembership::canAccessMembersContent($user, (int) $lockedEvent->tenant_id),
                    403
                );
            }

            $existing = EventRsvp::where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && ! in_array($existing->status, ['cancelled', 'rejected'], true)) {
                return back()->with('status', 'You already have an RSVP for this event.');
            }

            $status = $lockedEvent->rsvp_mode === 'instant' ? 'approved' : 'pending';

            if ($status === 'approved' && $lockedEvent->capacity !== null) {
                $approved = (int) EventRsvp::where('event_id', $lockedEvent->id)
                    ->where('status', 'approved')
                    ->sum('guest_count');

                if ($approved + (int) $data['guest_count'] > (int) $lockedEvent->capacity) {
                    if (! $lockedEvent->waitlist_enabled) {
                        abort(422, 'This event is full.');
                    }

                    DB::table('event_waitlist')->updateOrInsert(
                        ['event_id' => $lockedEvent->id, 'user_id' => $user->id],
                        [
                            'guest_count' => (int) $data['guest_count'],
                            'position' => (int) DB::table('event_waitlist')->where('event_id', $lockedEvent->id)->max('position') + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    return back()->with('status', 'Event is full; you were added to the waitlist.');
                }
            }

            EventRsvp::updateOrCreate(
                ['event_id' => $lockedEvent->id, 'user_id' => $user->id],
                [
                    'guest_count' => (int) $data['guest_count'],
                    'answers' => $data['answers'] ?? [],
                    'status' => $status,
                    'approved_at' => $status === 'approved' ? now() : null,
                ]
            );

            DB::table('event_waitlist')
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->delete();

            return back()->with('status', $status === 'approved' ? 'RSVP confirmed.' : 'RSVP submitted for approval.');
        });
    }

    public function cancel(Request $request, TenantContext $context, Event $event)
    {
        $tenantId = $context->check() ? $context->id() : null;
        if ($tenantId !== null) {
            abort_unless((int) $event->tenant_id === (int) $tenantId, 404);
        }

        DB::transaction(function () use ($request, $event, $tenantId): void {
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($tenantId !== null) {
                abort_unless((int) $lockedEvent->tenant_id === (int) $tenantId, 404);
            }

            EventRsvp::where('event_id', $lockedEvent->id)
                ->where('user_id', $request->user()->id)
                ->whereNotIn('status', ['cancelled'])
                ->update(['status' => 'cancelled', 'approved_at' => null]);

            DB::table('event_waitlist')
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $request->user()->id)
                ->delete();
        });

        return back()->with('status', 'RSVP cancelled.');
    }
}
