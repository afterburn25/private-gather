<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TenantSubscription extends Model{
 protected $fillable=['tenant_id','plan_id','status','provider','provider_reference','trial_ends_at','current_period_ends_at','cancelled_at','metadata'];
 protected function casts():array{return ['trial_ends_at'=>'datetime','current_period_ends_at'=>'datetime','cancelled_at'=>'datetime','metadata'=>'array'];}
 public function plan(){return $this->belongsTo(Plan::class);}
 public function tenant(){return $this->belongsTo(Tenant::class);}
}