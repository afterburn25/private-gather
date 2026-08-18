<?php
namespace App\Contracts;
use App\Models\TenantDomain;
interface DomainProvisioner{
 /** Ask the edge/hosting layer to accept and obtain TLS for a verified domain. */
 public function provision(TenantDomain $domain):array;
}