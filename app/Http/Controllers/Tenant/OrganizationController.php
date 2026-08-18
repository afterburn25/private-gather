<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Services\TenantProvisioner;use Illuminate\Http\Request;
class OrganizationController extends Controller {
 public function index(Request $r){return view('tenant.manage.organizations',['tenants'=>$r->user()->tenants()->with('domains')->get()]);}
 public function create(){return view('tenant.manage.create');}
 public function store(Request $r,TenantProvisioner $p){$d=$r->validate(['name'=>'required|string|max:150','type'=>'required|in:club,organizer,private_host','subdomain'=>'required|string|max:63|regex:/^[a-z0-9-]+$/']);$t=$p->create($d['name'],$d['type'],$d['subdomain'],$r->user());return redirect('https://'.$t->primaryDomain->domain.'/manage');}
}
