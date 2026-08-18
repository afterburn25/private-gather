<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Plan extends Model{protected $fillable=['code','name','price_monthly_cents','currency','features','active','sort_order'];protected function casts():array{return ['features'=>'array','active'=>'boolean'];}}