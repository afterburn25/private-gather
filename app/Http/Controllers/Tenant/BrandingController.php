<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Models\TenantBranding;use App\Tenancy\TenantContext;use Illuminate\Http\Request;
class BrandingController extends Controller{
 public function edit(TenantContext $ctx){$tenant=$ctx->requireTenant();$branding=TenantBranding::firstOrCreate(['tenant_id'=>$tenant->id]);return view('tenant.manage.branding',compact('tenant','branding'));}
 public function update(Request $r,TenantContext $ctx){$tenant=$ctx->requireTenant();$d=$r->validate(['logo_path'=>'nullable|string|max:1000','favicon_path'=>'nullable|string|max:1000','primary_color'=>['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],'accent_color'=>['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],'font_family'=>'nullable|string|max:120','email_from_name'=>'nullable|string|max:255','show_platform_branding'=>'nullable|boolean']);TenantBranding::updateOrCreate(['tenant_id'=>$tenant->id],$d+['show_platform_branding'=>$r->boolean('show_platform_branding')]);return back()->with('status','Branding saved.');}
}