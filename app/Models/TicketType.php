<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketType extends Model{
 protected $fillable=['event_id','name','description','price_cents','currency','quantity','max_per_order','sales_start_at','sales_end_at','active'];
 protected function casts():array{return ['sales_start_at'=>'datetime','sales_end_at'=>'datetime','active'=>'boolean'];}
 public function event(){return $this->belongsTo(Event::class);}public function orderItems(){return $this->hasMany(OrderItem::class);}
}