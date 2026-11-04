<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;use Illuminate\Database\Eloquent\Relations\HasMany;
class Order extends Model{
 protected $fillable=['public_id','tenant_id','event_id','user_id','status','currency','subtotal_cents','discount_cents','fee_cents','total_cents','promo_code'];
 public function items():HasMany{return $this->hasMany(OrderItem::class);}public function payments():HasMany{return $this->hasMany(Payment::class);}
 public function event():BelongsTo{return $this->belongsTo(Event::class);}public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}public function user():BelongsTo{return $this->belongsTo(User::class);}
}