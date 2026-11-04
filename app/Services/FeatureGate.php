<?php
namespace App\Services;
use App\Models\Tenant;
final class FeatureGate{
 public function plan(Tenant $tenant):array{
  $subscription=$tenant->subscription()->with('plan')->first();
  return (array)($subscription?->plan?->features??[]);
 }
 public function allows(Tenant $tenant,string $feature):bool{
  $features=$this->plan($tenant);$value=data_get($features,$feature,false);
  return $value===true || (is_numeric($value)&&(int)$value>0) || $value==='unlimited';
 }
 public function limit(Tenant $tenant,string $feature,?int $default=null):?int{
  $value=data_get($this->plan($tenant),$feature,$default);
  if($value==='unlimited')return null;return is_numeric($value)?(int)$value:$default;
 }
 public function assert(Tenant $tenant,string $feature):void{abort_unless($this->allows($tenant,$feature),403,'Your current plan does not include this feature.');}
}