<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ticket extends Model{
 protected $fillable=['public_id','order_item_id','user_id','qr_token','status','checked_in_at'];
 protected function casts():array{return ['checked_in_at'=>'datetime'];}
 public function orderItem(){return $this->belongsTo(OrderItem::class);}public function user(){return $this->belongsTo(User::class);}
}