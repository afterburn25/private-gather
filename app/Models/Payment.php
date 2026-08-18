<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class Payment extends Model{protected $fillable=['order_id','provider','provider_reference','status','amount_cents','currency','metadata','paid_at'];protected function casts():array{return['metadata'=>'array','paid_at'=>'datetime'];}}
