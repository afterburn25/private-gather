<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TicketIssuer
{
    public function issue(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            // The order row is the issuance mutex. Concurrent retries for the
            // same paid order serialize here, making ticket creation idempotent.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['paid', 'completed'], true), 422, 'Tickets can only be issued for paid orders.');

            $locked->load(['items.tickets']);
            $count = 0;

            foreach ($locked->items as $item) {
                $missing = max(0, (int) $item->quantity - $item->tickets->count());
                for ($i = 0; $i < $missing; $i++) {
                    Ticket::create([
                        'public_id' => (string) Str::uuid(),
                        'order_item_id' => $item->id,
                        'user_id' => $locked->user_id,
                        'qr_token' => bin2hex(random_bytes(24)),
                        'status' => 'valid',
                    ]);
                    $count++;
                }
            }

            return $count;
        });
    }
}
