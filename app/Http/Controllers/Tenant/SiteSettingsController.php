<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Models\SiteSetting;use App\Tenancy\TenantContext;use Illuminate\Http\Request;
class SiteSettingsController extends Controller{
 private const KEYS=['tagline','footer_text','contact_email','header_cta_label','header_cta_url','facebook_url','instagram_url','x_url'];
 public function edit(TenantContext $ctx){$t=$ctx->requireTenant();$settings=SiteSetting::where('tenant_id',$t->id)->pluck('value','key')->all();return view('tenant.manage.site-settings',compact('t','settings'));}
 public function update(Request $r,TenantContext $ctx){$t=$ctx->requireTenant();$d=$r->validate(['tagline'=>'nullable|string|max:500','footer_text'=>'nullable|string|max:1000','contact_email'=>'nullable|email|max:190','header_cta_label'=>'nullable|string|max:80','header_cta_url'=>'nullable|string|max:500','facebook_url'=>'nullable|url|max:500','instagram_url'=>'nullable|url|max:500','x_url'=>'nullable|url|max:500']);foreach(self::KEYS as $key)SiteSetting::updateOrCreate(['tenant_id'=>$t->id,'key'=>$key],['group'=>'general','value'=>$d[$key]??null,'type'=>'string','is_public'=>true]);$tenantSettings=$t->settings??[];$tenantSettings['marketplace_enabled']=$r->boolean('marketplace_enabled');$t->update(['settings'=>$tenantSettings]);return back()->with('status','Website settings saved.');}
}