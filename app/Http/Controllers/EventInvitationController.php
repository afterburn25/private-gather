<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventRsvp;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventInvitationController extends Controller
{
    public function show(Request $request, TenantContext $context, string $token)
    {
        $invite = $this->findInvite($token, false)->load('event.tenant');
        abort_unless($invite->isUsable(), 410, 'This invitation is no longer available.');
        abort_unless($invite->event->status === 'published', 404);
        $this->assertTenantContext($context, (int) $invite->event->tenant_id);
        $this->assertRecipient($request, $invite);

        return view('events.invitation', ['invite' => $invite, 'invitationToken' => $token]);
    }

    public function accept(Request $request, TenantContext $context, string $token)
    {
        $invite = $this->findInvite($token, true)->load('event');
        abort_unless($invite->isUsable(), 410, 'This invitation is no longer available.');
        $this->assertTenantContext($context, (int) $invite->event->tenant_id);
        $this->assertRecipient($request, $invite);
        abort_unless($request->user()->isAdult(), 403, 'An adult account is required.');
        $validated = $request->validate(['guest_count' => 'required|integer|min:1|max:'.max(1, (int) $invite->max_guests)]);
        $tenantId = $context->check() ? $context->id() : null;

        DB::transaction(function () use ($request, $invite, $validated, $tenantId): void {
            $locked = EventInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isUsable(), 410, 'This invitation is no longer available.');
            $event = Event::whereKey($locked->event_id)->lockForUpdate()->firstOrFail();
            abort_unless($event->status === 'published', 404);
            if ($tenantId !== null) {
                abort_unless((int) $event->tenant_id === (int) $tenantId, 404);
            }
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

    private function findInvite(string $token, bool $lock): EventInvitation
    {
        // New invitation links are 256-bit bearer secrets encoded as 64 hex
        // characters. Historical links used Laravel's 64-character alpha-
        // numeric generator. Reject anything outside those formats before it
        // reaches the database.
        abort_unless((bool) preg_match('/^[A-Za-z0-9]{64}$/D', $token), 404);

        $digest = 'sha256:'.hash('sha256', $token);
        $query = EventInvitation::query()->where(function ($query) use ($digest, $token): void {
            // The plaintext fallback is intentionally transitional. It keeps
            // an already-issued invitation usable if application files are
            // deployed immediately before the data-hardening migration runs.
            $query->where('token', $digest)->orWhere('token', $token);
        });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function assertTenantContext(TenantContext $context, int $tenantId): void
    {
        if ($context->check()) {
            abort_unless((int) $context->id() === $tenantId, 404);
        }
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
