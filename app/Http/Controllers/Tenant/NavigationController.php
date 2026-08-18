<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;use App\Models\CmsNavigationItem;use App\Tenancy\TenantContext;use Illuminate\Http\Request;
class NavigationController extends Controller{
 public function index(TenantContext $ctx){$t=$ctx->requireTenant();return view('tenant.manage.navigation',['items'=>CmsNavigationItem::where('tenant_id',$t->id)->orderBy('location')->orderBy('sort_order')->get()]);}
 public function store(Request $r,TenantContext $ctx){$t=$ctx->requireTenant();$d=$r->validate(['location'=>'required|in:header,footer','label'=>'required|string|max:80','url'=>'required|string|max:500','sort_order'=>'nullable|integer|min:0|max:10000']);CmsNavigationItem::create(['tenant_id'=>$t->id,'location'=>$d['location'],'label'=>$d['label'],'url'=>$d['url'],'sort_order'=>$d['sort_order']??0,'is_enabled'=>true]);return back()->with('status','Navigation item added.');}
 public function update(Request $r,TenantContext $ctx,CmsNavigationItem $item){abort_unless($item->tenant_id===$ctx->id(),404);$d=$r->validate(['label'=>'required|string|max:80','url'=>'required|string|max:500','sort_order'=>'required|integer|min:0|max:10000','is_enabled'=>'nullable|boolean']);$item->update($d+['is_enabled'=>$r->boolean('is_enabled')]);return back()->with('status','Navigation updated.');}
 public function destroy(TenantContext $ctx,CmsNavigationItem $item){abort_unless($item->tenant_id===$ctx->id(),404);$item->delete();return back()->with('status','Navigation item removed.');}
}