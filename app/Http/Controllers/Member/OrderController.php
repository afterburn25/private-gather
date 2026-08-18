<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;use App\Models\Order;use App\Models\Ticket;use Illuminate\Http\Request;
class OrderController extends Controller{
 public function index(Request $r){return view('member.orders.index',['orders'=>Order::where('user_id',$r->user()->id)->with('event')->latest()->paginate(30)]);}
 public function show(Request $r,Order $order){abort_unless($order->user_id===$r->user()->id,403);$order->load(['event','items.ticketType','items.tickets']);return view('member.orders.show',compact('order'));}
 public function tickets(Request $r){return view('member.tickets',['tickets'=>Ticket::where('user_id',$r->user()->id)->with('orderItem.order.event')->latest()->paginate(50)]);}
}