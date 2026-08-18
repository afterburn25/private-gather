<?php
namespace App\Services;
use App\Contracts\DomainProvisioner;use App\Models\TenantDomain;
final class DomainHealthService{
 public function __construct(private DomainProvisioner $provisioner){}
 public function verify(TenantDomain $domain):array{
  if($domain->type===TenantDomain::TYPE_PLATFORM_SUBDOMAIN){$result=['verified'=>true,'dns_status'=>'active','records'=>[]];}
  else{
   $record=(string)config('platform.domain_verification.txt_prefix').'.'.$domain->domain;$records=function_exists('dns_get_record')?(dns_get_record($record,DNS_TXT)?:[]):[];$values=[];
   foreach($records as $r)foreach(($r['entries']??[($r['txt']??'')]) as $v)if($v!=='')$values[]=$v;
   $verified=in_array((string)$domain->verification_token,$values,true);
   $result=['verified'=>$verified,'dns_status'=>$verified?'verified':'waiting','records'=>$values];
  }
  $domain->forceFill(['dns_status'=>$result['dns_status'],'dns_last_checked_at'=>now(),'health'=>$result,'last_error'=>$result['verified']?null:'Verification TXT record not detected yet.'])->save();
  if($result['verified']){
   $edge=$this->provisioner->provision($domain);
   $domain->forceFill(['verified_at'=>$domain->verified_at??now(),'status'=>TenantDomain::STATUS_ACTIVE,'ssl_status'=>$edge['ssl_status']??$domain->ssl_status,'last_error'=>null])->save();
   $result['provisioner']=$edge;
  }
  return $result;
 }
}