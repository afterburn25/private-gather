<?php
namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\TicketIssuer;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function store(
        Request $request,
        TenantContext $context,
        Event $event,
        TicketType $ticketType,
        PaymentGateway $gateway,
        TicketIssuer $issuer
    ) {
        abort_unless($event->status === 'published' && $ticketType->event_id === $event->id && $ticketType->active, 404);

        if ($context->check()) {
            abort_unless($context->id() === $event->tenant_id, 404);
        } else {
            abort_unless($event->visibility === 'public', 404);
        }

        if (in_array($event->visibility, ['private', 'invite_only'], true)) {
            abort_unless(
                $event->rsvps()->where('user_id', $request->user()->id)->where('status', 'approved')->exists(),
                403
            );
        }

        $data = $request->validate(['quantity' => 'required|integer|min:1|max:20']);
        $quantity = (int) $data['quantity'];
        abort_if($quantity > (int) $ticketType->max_per_order, 422, 'Quantity exceeds the ticket limit.');

        $order = DB::transaction(function () use ($event, $ticketType, $quantity, $request, $gateway, $issuer) {
            $type = TicketType::whereKey($ticketType->id)->lockForUpdate()->firstOrFail();
            abort_unless($type->event_id === $event->id && $type->active, 404);

            if ($type->sales_start_at && now()->lt($type->sales_start_at)) {
                abort(422, 'Ticket sales have not started.');
            }
            if ($type->sales_end_at && now()->gt($type->sales_end_at)) {
                abort(422, 'Ticket sales have ended.');
            }

            if ($type->quantity !== null) {
                $sold = (int) OrderItem::where('ticket_type_id', $type->id)
                    ->whereHas('order', fn ($query) => $query->whereIn('status', ['paid', 'completed']))
                    ->sum('quantity');

                abort_if($sold + $quantity > (int) $type->quantity, 422, 'Not enough tickets remain.');
            }

            $subtotal = (int) $type->price_cents * $quantity;
            $order = Order::create([
                'public_id' => (string) Str::uuid(),
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'status' => $subtotal === 0 ? 'completed' : 'pending',
                'currency' => $type->currency,
                'subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => $subtotal,
            ]);

            $order->items()->create([
                'ticket_type_id' => $type->id,
                'quantity' => $quantity,
                'unit_price_cents' => $type->price_cents,
                'total_cents' => $subtotal,
            ]);

            if ($subtotal === 0) {
                // Free inventory and ticket issuance commit atomically.
                $issuer->issue($order);
                return $order;
            }

            $result = $gateway->begin($order);
            $paymentStatus = (string) ($result['status'] ?? 'pending');
            $paid = in_array($paymentStatus, ['paid', 'completed'], true);

            Payment::create([
                'order_id' => $order->id,
                'provider' => $gateway->name(),
                'provider_reference' => $result['reference'] ?? null,
                'status' => $paid ? 'paid' : $paymentStatus,
                'amount_cents' => $subtotal,
                'currency' => $type->currency,
                'metadata' => $result,
                'paid_at' => $paid ? now() : null,
            ]);

            if ($paid) {
                $order->update(['status' => 'paid']);
                $issuer->issue($order);
            } elseif (in_array($paymentStatus, ['failed', 'cancelled'], true)) {
                $order->update(['status' => $paymentStatus]);
            }

            return $order->fresh();
        });

        return redirect()->route('member.orders.show', $order)->with(
            'status',
            in_array($order->status, ['paid', 'completed'], true)
                ? 'Tickets issued.'
                : ($order->status === 'pending'
                    ? 'Order created. Payment is pending organizer/payment-provider confirmation.'
                    : 'The payment was not completed.')
        );
    }
}
