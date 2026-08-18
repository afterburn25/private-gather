<?php
namespace App\Http\Middleware;
use App\Services\TenantDomainCache;use App\Support\DomainName;use App\Tenancy\TenantContext;use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
class ResolveTenantByDomain{
 public function __construct(private readonly TenantContext $context,private readonly TenantDomainCache $domains){}
 public function handle(Request $request,Closure $next):Response{
  $host=DomainName::normalize($request->getHost());$centralDomains=array_map('strtolower',config('platform.central_domains',[]));
  if(in_array($host,$centralDomains,true)){$this->context->clear();return $next($request);}
  $domain=$this->domains->find($host);
  if(!$domain||!$domain->tenant||!$domain->tenant->isActive())return response()->view('errors.tenant-domain',['host'=>$host,'domainTarget'=>config('platform.domain_target')],404);
  $this->context->set($domain->tenant);$request->attributes->set('tenant',$domain->tenant);$request->attributes->set('tenant_domain',$domain);return $next($request);
 }
}