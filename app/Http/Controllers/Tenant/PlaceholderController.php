<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Services\PlaceholderRenderer;use App\Tenancy\TenantContext;
class PlaceholderController extends Controller{
 public function index(TenantContext $ctx,PlaceholderRenderer $r){$tenant=$ctx->requireTenant();$sample=(object)['title'=>'Sample Event','starts_at'=>now()->addWeek(),'public_city'=>'Your City','capacity'=>100];return view('tenant.manage.cms.placeholders',['tenant'=>$tenant,'placeholders'=>$r->available($r->eventContext($sample,$tenant,auth()->user()))]);}
}