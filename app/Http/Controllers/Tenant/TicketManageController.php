<?php
namespace App\Http\Controllers\Tenant;
use App\Models\Event;use App\Tenancy\TenantContext;use Illuminate\Http\Request;
class TicketManageController {
 public function store(Request $r,TenantContext $c,Event $event){abort_unless($event->tenant_id===$c->id(),404);$d=$r->validate(['name'=>'required|string|max:120','description'=>'nullable|string|max:1000','price'=>'required|numeric|min:0','quantity'=>'nullable|integer|min:1','max_per_order'=>'required|integer|min:1|max:100']);$event->ticketTypes()->create(['name'=>$d['name'],'description'=>$d['description']??null,'price_cents'=>(int)round(((float)$d['price'])*100),'quantity'=>$d['quantity']??null,'max_per_order'=>$d['max_per_order'],'currency'=>'USD','active'=>true]);return back()->with('status','Ticket type created.');}
}
