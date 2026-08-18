<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Models\Order;use App\Services\TicketIssuer;use App\Tenancy\TenantContext;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;
class OrderManageController extends Controller{
 public function index(TenantContext $ctx){$t=$ctx->requireTenant();return view('tenant.manage.orders.index',['orders'=>Order::where('tenant_id',$t->id)->with(['user','event'])->latest()->paginate(100)]);}
 public function markPaid(Request $r,TenantContext $ctx,Order $order,TicketIssuer $issuer){abort_unless($order->tenant_id===$ctx->id(),404);abort_unless(in_array($order->status,['pending','processing'],true),422);DB::transaction(function()use($order){$order->update(['status'=>'paid']);$order->payments()->where('status','pending')->update(['status'=>'paid','paid_at'=>now()]);});$issuer->issue($order);return back()->with('status','Order marked paid and tickets issued.');}
}