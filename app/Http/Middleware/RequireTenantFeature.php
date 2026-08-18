<?php
namespace App\Http\Middleware;
use App\Services\FeatureGate;use App\Tenancy\TenantContext;use Closure;use Illuminate\Http\Request;
class RequireTenantFeature{
 public function handle(Request $request,Closure $next,string $feature){$tenant=app(TenantContext::class)->requireTenant();app(FeatureGate::class)->assert($tenant,$feature);return $next($request);}
}