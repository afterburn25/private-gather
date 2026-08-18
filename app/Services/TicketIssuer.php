<?php
namespace App\Services;
use App\Models\Order;use App\Models\Ticket;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;
final class TicketIssuer{
 public function issue(Order $order):int{
  return DB::transaction(function()use($order){$order->load('items.tickets');$count=0;foreach($order->items as $item){$missing=max(0,$item->quantity-$item->tickets->count());for($i=0;$i<$missing;$i++){Ticket::create(['public_id'=>(string)Str::uuid(),'order_item_id'=>$item->id,'user_id'=>$order->user_id,'qr_token'=>bin2hex(random_bytes(24)),'status'=>'valid']);$count++;}}return $count;});
 }
}