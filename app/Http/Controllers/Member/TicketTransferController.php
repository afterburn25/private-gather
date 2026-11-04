<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TenantDomain;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\ProductCompletionService;
use App\Support\Audit;
use App\Support\TenantMembership;
use App\Support\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TicketTransferController extends Controller
{
    public function store(Request $request, Ticket $ticket, ProductCompletionService $products): RedirectResponse
    {
        $ticket->loadMissing(['orderItem.order.event', 'orderItem.ticketType']);
        $order = $ticket->orderItem?->order;
        $event = $order?->event;
        $type = $ticket->orderItem?->ticketType;
        abort_unless($order && $event && $type && (int) $ticket->user_id === (int) $request->user()->id, 404);
        $this->assertTransferable($ticket);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $email = Str::lower(trim($data['email']));
        $recipient = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $recipient) throw ValidationException::withMessages(['email' => 'Ticket transfers currently require an existing Private Gather account for that email address.']);
        abort_unless($recipient->status === 'active', 422, 'The recipient account is not active.');
        abort_if((int) $recipient->id === (int) $request->user()->id, 422, 'Choose a different Private Gather account for the transfer.');
        $this->assertEligible($recipient, $event, $type);

        $domain = TenantDomain::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('is_primary', true)
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->whereNotNull('verified_at')
            ->first();
        if (! $domain) throw ValidationException::withMessages(['email' => 'This organization needs a verified primary site address before secure ticket transfers can be sent.']);

        $raw = Str::random(64);
        $transferId = DB::transaction(function () use ($request, $ticket, $order, $event, $recipient, $data, $raw): int {
            $lockedTicket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedTicket->user_id === (int) $request->user()->id, 409, 'Ticket ownership changed before the transfer could be created.');
            $this->assertTransferable($lockedTicket);

            DB::table('ticket_transfers')
                ->where('ticket_id', $lockedTicket->id)
                ->where('status', 'pending')
                ->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);

            return DB::table('ticket_transfers')->insertGetId([
                'ticket_id' => $lockedTicket->id,
                'tenant_id' => $order->tenant_id,
                'event_id' => $event->id,
                'from_user_id' => $request->user()->id,
                'to_user_id' => $recipient->id,
                'initiated_by' => $request->user()->id,
                'token_digest' => hash('sha256', $raw),
                'status' => 'pending',
                'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
                'expires_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $url = TenantUrl::to($domain, '/ticket-transfer/'.$raw);
        $products->notify($recipient->id, 'ticket.transfer.pending', [
            'label' => 'Ticket transfer offered for '.$event->title,
            'ticket_transfer_id' => $transferId,
            'event_id' => $event->id,
            'tenant_id' => $order->tenant_id,
            'transfer_url' => $url,
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]);
        Audit::write('ticket.transfer.created', $ticket, after: ['transfer_id' => $transferId, 'to_user_id' => $recipient->id, 'event_id' => $event->id], tenantId: $order->tenant_id, request: $request);

        return back()->with('status', 'Ticket transfer offered to '.$email.'. The recipient must accept before ownership changes.')
            ->with('ticket_transfer_link', $url);
    }

    public function show(Request $request, string $token): View
    {
        $transfer = $this->resolve($token, $request->user());
        $ticket = Ticket::query()->with(['orderItem.order.event', 'orderItem.ticketType'])->findOrFail($transfer->ticket_id);
        return view('member.ticket-transfer', compact('transfer', 'ticket', 'token'));
    }

    public function accept(Request $request, string $token, ProductCompletionService $products): RedirectResponse
    {
        $user = $request->user();
        $result = DB::transaction(function () use ($request, $token, $user): array {
            $transfer = DB::table('ticket_transfers')->where('token_digest', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($transfer, 404);
            $this->expireIfDue($transfer);
            $transfer = DB::table('ticket_transfers')->where('id', $transfer->id)->lockForUpdate()->first();
            abort_unless($transfer && $transfer->status === 'pending', 410, 'This ticket transfer is no longer active.');
            abort_unless((int) $transfer->to_user_id === (int) $user->id, 403, 'This ticket transfer belongs to a different Private Gather account.');

            $ticket = Ticket::query()->whereKey($transfer->ticket_id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing(['orderItem.order.event', 'orderItem.ticketType']);
            abort_unless((int) $ticket->user_id === (int) $transfer->from_user_id, 409, 'Ticket ownership changed before this transfer was accepted.');
            $this->assertTransferable($ticket);
            $event = $ticket->orderItem->order->event;
            $type = $ticket->orderItem->ticketType;
            $this->assertEligible($user, $event, $type);

            $ticket->update([
                'user_id' => $user->id,
                'qr_token' => bin2hex(random_bytes(24)),
            ]);
            DB::table('ticket_transfers')->where('id', $transfer->id)->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

            Audit::write('ticket.transfer.accepted', $ticket, before: ['user_id' => $transfer->from_user_id], after: ['user_id' => $user->id, 'transfer_id' => $transfer->id, 'qr_rotated' => true], tenantId: $transfer->tenant_id, request: $request);
            return ['from_user_id' => (int) $transfer->from_user_id, 'event_id' => (int) $transfer->event_id, 'tenant_id' => (int) $transfer->tenant_id, 'ticket_id' => $ticket->id, 'event_title' => $event->title];
        });

        $products->notify($result['from_user_id'], 'ticket.transfer.accepted', [
            'label' => 'Ticket transfer accepted for '.$result['event_title'],
            'event_id' => $result['event_id'],
            'tenant_id' => $result['tenant_id'],
            'ticket_id' => $result['ticket_id'],
        ]);

        return redirect()->route('member.tickets')->with('status', 'Ticket transfer accepted. A new QR credential was issued to you and the sender’s old QR is invalid.');
    }

    public function revoke(Request $request, int $transfer): RedirectResponse
    {
        DB::transaction(function () use ($request, $transfer): void {
            $row = DB::table('ticket_transfers')->where('id', $transfer)->lockForUpdate()->first();
            abort_unless($row && (int) $row->from_user_id === (int) $request->user()->id, 404);
            abort_unless($row->status === 'pending', 422, 'Only pending transfers can be revoked.');
            DB::table('ticket_transfers')->where('id', $row->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);
            Audit::write('ticket.transfer.revoked', null, after: ['transfer_id' => $row->id, 'ticket_id' => $row->ticket_id], tenantId: $row->tenant_id, request: $request);
        });
        return back()->with('status', 'Pending ticket transfer revoked.');
    }

    private function resolve(string $token, User $user): object
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);
        $transfer = DB::table('ticket_transfers')->where('token_digest', hash('sha256', $token))->first();
        abort_unless($transfer, 404);
        $this->expireIfDue($transfer);
        $transfer = DB::table('ticket_transfers')->where('id', $transfer->id)->first();
        abort_unless($transfer && $transfer->status === 'pending', 410, 'This ticket transfer is no longer active.');
        abort_unless((int) $transfer->to_user_id === (int) $user->id, 403, 'This ticket transfer belongs to a different Private Gather account.');
        return $transfer;
    }

    private function expireIfDue(object $transfer): void
    {
        if ($transfer->status === 'pending' && $transfer->expires_at && now()->gte($transfer->expires_at)) {
            DB::table('ticket_transfers')->where('id', $transfer->id)->where('status', 'pending')->update(['status' => 'expired', 'updated_at' => now()]);
        }
    }

    private function assertTransferable(Ticket $ticket): void
    {
        abort_unless($ticket->status === 'valid' && $ticket->checked_in_at === null, 422, 'Only unused valid tickets can be transferred.');
    }

    private function assertEligible(User $user, Event $event, TicketType $type): void
    {
        if ($event->visibility === 'members' || $type->membership_required) {
            abort_unless(TenantMembership::hasActiveMembership($user, (int) $event->tenant_id), 403, 'The recipient needs an active membership for this ticket.');
        }
        if (in_array($event->visibility, ['private', 'invite_only'], true) || $type->approval_required) {
            abort_unless($event->rsvps()->where('user_id', $user->id)->where('status', 'approved')->exists(), 403, 'The recipient must have approved attendance for this event.');
        }
        if ($type->profile_eligibility !== TicketType::ELIGIBILITY_ANY) {
            abort_unless($user->profile?->profile_type === $type->profile_eligibility, 403, 'The recipient profile type is not eligible for this admission type.');
        }
    }
}
