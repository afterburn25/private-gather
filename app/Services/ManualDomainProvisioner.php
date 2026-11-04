<?php
namespace App\Services;
use App\Contracts\DomainProvisioner;use App\Models\TenantDomain;
final class ManualDomainProvisioner implements DomainProvisioner{
 public function provision(TenantDomain $domain):array{return ['accepted'=>true,'ssl_status'=>'pending','message'=>'Domain verified. Configure the edge/TLS provider to provision HTTPS.'];}
}