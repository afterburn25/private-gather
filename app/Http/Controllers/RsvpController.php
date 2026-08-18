<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RsvpController extends Controller
{
    public function store(Request $request, TenantContext $context, Event $event)
    {
        abort_unless($event->status === 'published', 404);
        if ($context->check()) {
            abort_unless($context->id() === $event->tenant_id, 404);
        } else {
            abort_unless($event->visibility === 'public', 404);
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

        return DB::transaction(function () use ($event, $user, $data) {
            // Serialize capacity decisions for this event. Without an event-row
            // lock, simultaneous instant RSVPs can both see the same free seats.
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedEvent->status === 'published', 404);

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

    public function cancel(Request $request, Event $event)
    {
        DB::transaction(function () use ($request, $event): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            EventRsvp::where('event_id', $event->id)
                ->where('user_id', $request->user()->id)
                ->whereNotIn('status', ['cancelled'])
                ->update(['status' => 'cancelled', 'approved_at' => null]);

            DB::table('event_waitlist')
                ->where('event_id', $event->id)
                ->where('user_id', $request->user()->id)
                ->delete();
        });

        return back()->with('status', 'RSVP cancelled.');
    }
}
