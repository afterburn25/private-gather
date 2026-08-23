<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\ProductCompletionService;
use App\Services\TicketIssuer;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function store(Request $request, TenantContext $context, Event $event, TicketType $ticketType, PaymentGateway $gateway, TicketIssuer $issuer, ProductCompletionService $products)
    {
        abort_unless($event->status === 'published' && $ticketType->event_id === $event->id && $ticketType->active, 404);
        $tenantId = $context->check() ? $context->id() : null;
        if ($tenantId !== null) abort_unless((int) $tenantId === (int) $event->tenant_id, 404); else abort_unless($event->visibility === 'public', 404);
        if (in_array($event->visibility, ['private', 'invite_only'], true)) abort_unless($event->rsvps()->where('user_id', $request->user()->id)->where('status', 'approved')->exists(), 403);
        $this->enforceTicketEligibility($request, $event, $ticketType);
        $quantity = (int) $request->validate(['quantity' => 'required|integer|min:1|max:20'])['quantity'];
        abort_if($quantity > (int) $ticketType->max_per_order, 422, 'Quantity exceeds the ticket limit.');

        $order = DB::transaction(function () use ($event, $ticketType, $quantity, $request, $gateway, $issuer, $tenantId) {
            $type = TicketType::whereKey($ticketType->id)->lockForUpdate()->firstOrFail();
            abort_unless($type->event_id === $event->id && $type->active, 404);
            if ($tenantId !== null) abort_unless((int) $event->tenant_id === (int) $tenantId, 404);
            $this->enforceTicketEligibility($request, $event, $type);
            if ($type->sales_start_at && now()->lt($type->sales_start_at)) abort(422, 'Ticket sales have not started.');
            if ($type->sales_end_at && now()->gt($type->sales_end_at)) abort(422, 'Ticket sales have ended.');
            if ($type->quantity !== null) {
                $sold = (int) OrderItem::where('ticket_type_id', $type->id)->whereHas('order', fn ($query) => $query->whereIn('status', ['paid', 'completed']))->sum('quantity');
                abort_if($sold + $quantity > (int) $type->quantity, 422, 'Not enough tickets remain.');
            }
            $subtotal = (int) $type->price_cents * $quantity;
            $order = Order::create(['public_id' => (string) Str::uuid(), 'tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'user_id' => $request->user()->id, 'status' => $subtotal === 0 ? 'completed' : 'pending', 'currency' => $type->currency, 'subtotal_cents' => $subtotal, 'discount_cents' => 0, 'fee_cents' => 0, 'total_cents' => $subtotal]);
            $order->items()->create(['ticket_type_id' => $type->id, 'quantity' => $quantity, 'unit_price_cents' => $type->price_cents, 'total_cents' => $subtotal]);
            if ($subtotal === 0) { $issuer->issue($order); return $order; }
            $result = $gateway->begin($order); $paymentStatus = (string) ($result['status'] ?? 'pending'); $paid = in_array($paymentStatus, ['paid', 'completed'], true);
            Payment::create(['order_id' => $order->id, 'provider' => $gateway->name(), 'provider_reference' => $result['reference'] ?? null, 'status' => $paid ? 'paid' : $paymentStatus, 'amount_cents' => $subtotal, 'currency' => $type->currency, 'metadata' => $result, 'paid_at' => $paid ? now() : null]);
            if ($paid) { $order->update(['status' => 'paid']); $issuer->issue($order); } elseif (in_array($paymentStatus, ['failed', 'cancelled'], true)) $order->update(['status' => $paymentStatus]);
            return $order->fresh();
        });

        $referralId = (int) $request->session()->get('private_gather_referral_code_id', 0);
        if ($referralId > 0) {
            DB::table('referral_attributions')->where('referral_code_id', $referralId)->where('session_key', hash('sha256', $request->session()->getId()))->whereNull('order_id')->latest('id')->limit(1)->update(['user_id' => $request->user()->id, 'tenant_id' => $order->tenant_id, 'event_id' => $order->event_id, 'order_id' => $order->id, 'updated_at' => now()]);
        }
        if (in_array($order->status, ['paid', 'completed'], true)) $products->recordPaidOrder($order->fresh(['tenant', 'payments']));

        return redirect()->route('member.orders.show', $order)->with('status', in_array($order->status, ['paid', 'completed'], true) ? 'Tickets issued.' : ($order->status === 'pending' ? 'Order created. Payment is pending organizer/payment-provider confirmation.' : 'The payment was not completed.'));
    }

    private function enforceTicketEligibility(Request $request, Event $event, TicketType $ticketType): void
    {
        $user = $request->user(); abort_unless($user, 401);
        if ($event->visibility === 'members' || $ticketType->membership_required) abort_unless(TenantMembership::hasActiveMembership($user, (int) $event->tenant_id), 403, 'This ticket is available only to active members of this club or organization.');
        if ($ticketType->profile_eligibility !== TicketType::ELIGIBILITY_ANY) {
            $profileType = $user->profile?->profile_type;
            abort_unless($profileType === $ticketType->profile_eligibility, 403, $ticketType->profile_eligibility === TicketType::ELIGIBILITY_COUPLE ? 'This admission type is reserved for couple profiles.' : 'This admission type is reserved for individual profiles.');
        }
        if ($ticketType->approval_required) abort_unless($event->rsvps()->where('user_id', $user->id)->where('status', 'approved')->exists(), 403, 'Your event attendance must be approved before this ticket can be purchased.');
    }
}
