<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return view('member.orders.index', [
            'orders' => Order::where('user_id', $request->user()->id)
                ->with('event')
                ->latest()
                ->paginate(30),
        ]);
    }

    public function show(Request $request, Order $order)
    {
        // Treat another member's order identifier as nonexistent. Returning a
        // 403 here leaks that a guessed order ID is valid on a privacy-focused
        // platform even though the caller cannot read the order itself.
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        $order->load(['event', 'items.ticketType', 'items.tickets']);

        return view('member.orders.show', compact('order'));
    }

    public function tickets(Request $request)
    {
        return view('member.tickets', [
            'tickets' => Ticket::where('user_id', $request->user()->id)
                ->with('orderItem.order.event')
                ->latest()
                ->paginate(50),
        ]);
    }
}
