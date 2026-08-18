<?php
namespace App\Services;
use App\Models\TenantDomain;use Illuminate\Support\Facades\Cache;
final class TenantDomainCache{
 public function find(string $host):?TenantDomain{
  $key='tenant-domain:v1:'.hash('sha256',$host);
  $id=Cache::remember($key,now()->addMinutes(5),fn()=>TenantDomain::where('domain',$host)->where('status',TenantDomain::STATUS_ACTIVE)->value('id')?:0);
  return $id?TenantDomain::with('tenant')->find($id):null;
 }
 public function forget(string $host):void{Cache::forget('tenant-domain:v1:'.hash('sha256',$host));}
}