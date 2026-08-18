<?php
namespace App\Services;
use App\Models\Tenant;use App\Models\TenantDomain;use App\Support\DomainName;use Illuminate\Support\Str;use InvalidArgumentException;
final class CustomDomainService{
 public function request(Tenant $tenant,string $domain):TenantDomain{
  $domain=DomainName::normalize($domain);
  if(in_array($domain,config('platform.central_domains',[]),true))throw new InvalidArgumentException('The platform domain cannot be claimed by a tenant.');
  if(TenantDomain::where('domain',$domain)->exists())throw new InvalidArgumentException('That domain is already connected to an account.');
  return $tenant->domains()->create(['domain'=>$domain,'type'=>str_contains($domain,'.')&&substr_count($domain,'.')>=2?TenantDomain::TYPE_CUSTOM_SUBDOMAIN:TenantDomain::TYPE_CUSTOM_DOMAIN,'is_primary'=>false,'status'=>TenantDomain::STATUS_PENDING,'verification_token'=>Str::random(48),'ssl_status'=>'pending','dns_status'=>'pending','redirect_to_primary'=>false]);
 }
 public function dnsInstructions(TenantDomain $domain):array{return ['domain'=>$domain->domain,'cname_target'=>config('platform.domain_target'),'verification_record'=>'_platform-verification.'.$domain->domain,'verification_value'=>$domain->verification_token,'note'=>'For an apex/root domain, use the ALIAS/ANAME/CNAME-flattening option offered by the DNS provider, or configure the A/AAAA target supplied by the hosting edge.'];}
}