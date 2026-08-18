<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventCheckin extends Model{
 protected $fillable=['event_id','user_id','ticket_id','checked_in_by','method','guest_count','checked_in_at','metadata'];
 protected function casts():array{return ['checked_in_at'=>'datetime','metadata'=>'array'];}
}