<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventRsvp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventInvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invite = EventInvitation::with('event.tenant')->where('token', $token)->firstOrFail();
        abort_unless($invite->isUsable(), 410, 'This invitation is no longer available.');
        abort_unless($invite->event->status === 'published', 404);
        $this->assertRecipient($request, $invite);

        return view('events.invitation', ['invite' => $invite]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = EventInvitation::with('event')->where('token', $token)->lockForUpdate()->firstOrFail();
        abort_unless($invite->isUsable(), 410, 'This invitation is no longer available.');
        $this->assertRecipient($request, $invite);
        abort_unless($request->user()->isAdult(), 403, 'An adult account is required.');
        $validated = $request->validate(['guest_count' => 'required|integer|min:1|max:'.max(1, (int) $invite->max_guests)]);

        DB::transaction(function () use ($request, $invite, $validated): void {
            $locked = EventInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isUsable(), 410, 'This invitation is no longer available.');
            $event = Event::whereKey($locked->event_id)->lockForUpdate()->firstOrFail();
            abort_unless($event->status === 'published', 404);
            if ($event->capacity !== null) {
                $approved = (int) EventRsvp::where('event_id', $event->id)->where('status', 'approved')->sum('guest_count');
                $existing = EventRsvp::where('event_id', $event->id)->where('user_id', $request->user()->id)->first();
                if ($existing && $existing->status === 'approved') {
                    $approved -= (int) $existing->guest_count;
                }
                abort_if($approved + (int) $validated['guest_count'] > (int) $event->capacity, 422, 'This event no longer has enough capacity for that party size.');
            }

            EventRsvp::updateOrCreate(
                ['event_id' => $locked->event_id, 'user_id' => $request->user()->id],
                ['status' => 'approved', 'guest_count' => (int) $validated['guest_count'], 'answers' => [], 'approved_at' => now()]
            );

            $locked->update([
                'status' => 'accepted',
                'user_id' => $request->user()->id,
                'accepted_at' => now(),
            ]);
        });

        return redirect()->route('events.show', $invite->event_id)->with('status', 'Invitation accepted.');
    }

    private function assertRecipient(Request $request, EventInvitation $invite): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        if ($invite->user_id !== null) {
            abort_unless((int) $invite->user_id === (int) $user->id, 403);
        }
        if ($invite->email) {
            abort_unless(strcasecmp($invite->email, (string) $user->email) === 0, 403, 'This invitation belongs to another email address.');
        }
    }
}
