<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\ProductCompletionService;
use App\Services\PromoCodeService;
use App\Services\TicketIssuer;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderManageController extends Controller
{
    public function index(TenantContext $context)
    {
        $tenant = $context->requireTenant();
        return view('tenant.manage.orders.index', ['orders' => Order::where('tenant_id', $tenant->id)->with(['user', 'event'])->latest()->paginate(100)]);
    }

    public function markPaid(Request $request, TenantContext $context, Order $order, TicketIssuer $issuer, ProductCompletionService $products, PromoCodeService $promos)
    {
        abort_unless($order->tenant_id === $context->id(), 404);
        DB::transaction(function () use ($order, $issuer, $promos): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['pending', 'processing'], true), 422, 'This order is no longer awaiting payment.');
            $event = $lockedOrder->event()->lockForUpdate()->firstOrFail();

            $items = $lockedOrder->items()->get()->groupBy('ticket_type_id');
            foreach ($items->keys()->map(fn ($id) => (int) $id)->sort()->values() as $ticketTypeId) {
                $type = TicketType::whereKey($ticketTypeId)->lockForUpdate()->firstOrFail();
                $requested = (int) $items[$ticketTypeId]->sum('quantity');
                if ($type->quantity !== null) {
                    $sold = (int) OrderItem::where('ticket_type_id', $type->id)->where('order_id', '!=', $lockedOrder->id)
                        ->whereHas('order', fn ($query) => $query->whereIn('status', ['paid', 'completed']))->sum('quantity');
                    abort_if($sold + $requested > (int) $type->quantity, 422, "Not enough {$type->name} tickets remain to mark this order paid.");
                }
            }

            $promoId = $promos->revalidateOrderPromo($lockedOrder, $event, true);
            if ($promoId) $promos->consume($promoId);

            $lockedOrder->update(['status' => 'paid']);
            $lockedOrder->payments()->where('status', 'pending')->update(['status' => 'paid', 'paid_at' => now(), 'amount_cents' => $lockedOrder->total_cents]);
            $issuer->issue($lockedOrder);
        });
        $products->recordPaidOrder($order->fresh(['tenant', 'payments']));
        return back()->with('status', 'Order marked paid, promo revalidated, tickets issued, and marketplace split recorded.');
    }
}
