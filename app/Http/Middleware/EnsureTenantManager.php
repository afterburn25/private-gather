<?php
namespace App\Http\Middleware;
use App\Tenancy\TenantContext;use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
class EnsureTenantManager {public function __construct(private TenantContext $context){} public function handle(Request $r,Closure $next):Response{$tenant=$this->context->requireTenant();abort_unless($r->user(),401);$membership=$r->user()->tenants()->whereKey($tenant->id)->first()?->pivot;abort_unless($r->user()->is_platform_admin || ($membership && in_array($membership->role,['owner','admin','manager'],true) && $membership->status==='active'),403);return $next($r);}}
