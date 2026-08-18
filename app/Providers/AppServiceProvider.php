<?php
namespace App\Providers;
use App\Contracts\DomainProvisioner;use App\Contracts\PaymentGateway;use App\Models\CmsNavigationItem;use App\Models\SiteSetting;use App\Services\PlatformContent;use App\Services\ManualDomainProvisioner;use App\Services\Payments\OfflinePaymentGateway;use App\Tenancy\TenantContext;use Illuminate\Support\Facades\View;use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider{
 public function register():void{$this->app->singleton(TenantContext::class,fn()=>new TenantContext());$this->app->bind(DomainProvisioner::class,ManualDomainProvisioner::class);$this->app->bind(PaymentGateway::class,OfflinePaymentGateway::class);}
 public function boot():void{
  View::composer('layouts.app',function($view){$tenant=app(TenantContext::class)->tenant();$settings=collect();$nav=collect();if($tenant){$tenant->loadMissing('branding');$settings=SiteSetting::where('tenant_id',$tenant->id)->where('is_public',true)->pluck('value','key');$nav=CmsNavigationItem::where('tenant_id',$tenant->id)->where('is_enabled',true)->orderBy('sort_order')->get()->groupBy('location');}else{$settings=collect(app(PlatformContent::class)->all());}$view->with(['layoutTenant'=>$tenant,'siteSettings'=>$settings,'tenantNavigation'=>$nav]);});
 }
}