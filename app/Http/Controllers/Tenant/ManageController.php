<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Tenancy\TenantContext;use Illuminate\Http\Request;
class ManageController extends Controller{
 public function index(Request $r,TenantContext $c){$t=$c->requireTenant()->loadCount('events')->load(['domains','subscription.plan','branding']);return view('tenant.manage.dashboard',['tenant'=>$t]);}
}