<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;use Illuminate\Database\Eloquent\Relations\HasMany;
class Event extends Model{
 use HasFactory;
 protected $fillable=['tenant_id','title','slug','summary','description','visibility','rsvp_mode','status','starts_at','ends_at','timezone','capacity','city','region','public_location_label','exact_address','exact_address_visibility','cover_image_path','category','dress_code','rules','schedule','waitlist_enabled','requires_verified_profile','registration_opens_at','registration_closes_at','recurrence_rule','recurrence_until','parent_event_id'];
 protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','capacity'=>'integer','schedule'=>'array','waitlist_enabled'=>'boolean','requires_verified_profile'=>'boolean','registration_opens_at'=>'datetime','registration_closes_at'=>'datetime','recurrence_until'=>'datetime'];}
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}public function rsvps():HasMany{return $this->hasMany(EventRsvp::class);}public function ticketTypes():HasMany{return $this->hasMany(TicketType::class);}public function orders():HasMany{return $this->hasMany(Order::class);}public function checkins():HasMany{return $this->hasMany(EventCheckin::class);}public function invitations():HasMany{return $this->hasMany(EventInvitation::class);}
 public function approvedGuestCount():int{return (int)$this->rsvps()->where('status','approved')->sum('guest_count');}public function remainingCapacity():?int{return $this->capacity===null?null:max(0,$this->capacity-$this->approvedGuestCount());}
}