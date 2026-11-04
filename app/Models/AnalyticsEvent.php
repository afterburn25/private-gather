<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnalyticsEvent extends Model{protected $fillable=['tenant_id','event_id','user_id','event_name','session_key','path','referrer','properties','occurred_at'];protected function casts():array{return ['properties'=>'array','occurred_at'=>'datetime'];}}