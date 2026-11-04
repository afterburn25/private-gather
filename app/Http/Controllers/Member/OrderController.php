<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $query = Order::query()->where('user_id', $request->user()->id);
        if ($context->check()) {
            $query->where('tenant_id', $context->id());
        }

        return view('member.orders.index', [
            'orders' => $query
                ->with('event')
                ->latest()
                ->paginate(30),
        ]);
    }

    public function show(Request $request, Order $order, TenantContext $context)
    {
        // Treat another member's order identifier as nonexistent. Returning a
        // 403 here leaks that a guessed order ID is valid on a privacy-focused
        // platform even though the caller cannot read the order itself.
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        if ($context->check()) {
            abort_unless((int) $order->tenant_id === (int) $context->id(), 404);
        }

        $order->load(['event', 'items.ticketType', 'items.tickets']);

        return view('member.orders.show', compact('order'));
    }

    public function tickets(Request $request, TenantContext $context)
    {
        $query = Ticket::query()->where('user_id', $request->user()->id);
        if ($context->check()) {
            $tenantId = $context->id();
            $query->whereHas('orderItem.order', fn ($order) => $order->where('tenant_id', $tenantId));
        }

        return view('member.tickets', [
            'tickets' => $query
                ->with('orderItem.order.event')
                ->latest()
                ->paginate(50),
        ]);
    }
}
