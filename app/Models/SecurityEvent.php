<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SecurityEvent extends Model{protected $fillable=['user_id','event','ip_hash','user_agent_hash','metadata','occurred_at'];protected function casts():array{return ['metadata'=>'array','occurred_at'=>'datetime'];}}