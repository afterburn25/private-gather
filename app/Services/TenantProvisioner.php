<?php
namespace App\Services;
use App\Models\CmsNavigationItem;use App\Models\Plan;use App\Models\SiteSetting;use App\Models\Tenant;use App\Models\TenantDomain;use App\Models\TenantSubscription;use App\Models\User;use App\Support\DomainName;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;use InvalidArgumentException;
final class TenantProvisioner{
 public function create(string $name,string $type,string $subdomain,?User $owner=null):Tenant{
  $subdomain=Str::slug($subdomain);$this->assertSubdomainAvailable($subdomain);
  return DB::transaction(function()use($name,$type,$subdomain,$owner):Tenant{
   $tenant=Tenant::create(['name'=>$name,'slug'=>$this->uniqueSlug($name),'type'=>$type,'status'=>'active','plan'=>'starter','settings'=>['marketplace_enabled'=>$type!==Tenant::TYPE_PRIVATE_HOST]]);
   $domain=DomainName::platformSubdomain($subdomain,config('platform.root_domain'));
   $tenant->domains()->create(['domain'=>$domain,'type'=>TenantDomain::TYPE_PLATFORM_SUBDOMAIN,'is_primary'=>true,'status'=>TenantDomain::STATUS_ACTIVE,'verified_at'=>now(),'ssl_status'=>'managed','dns_status'=>'active','dns_last_checked_at'=>now(),'redirect_to_primary'=>false]);
   if($owner)$tenant->users()->attach($owner->getKey(),['role'=>'owner','status'=>'active']);
   if($starter=Plan::where('code','starter')->first())TenantSubscription::create(['tenant_id'=>$tenant->id,'plan_id'=>$starter->id,'status'=>'active']);
   $this->createDefaultSite($tenant);return $tenant->fresh(['domains','pages.sections','subscription.plan']);
  });
 }
 private function assertSubdomainAvailable(string $subdomain):void{
  if($subdomain===''||in_array($subdomain,config('platform.reserved_subdomains',[]),true))throw new InvalidArgumentException('That Private Gather subdomain is reserved.');
  $domain=DomainName::platformSubdomain($subdomain,config('platform.root_domain'));if(TenantDomain::where('domain',$domain)->exists())throw new InvalidArgumentException('That Private Gather subdomain is already in use.');
 }
 private function uniqueSlug(string $name):string{$base=Str::slug($name)?:'site';$slug=$base;$suffix=2;while(Tenant::where('slug',$slug)->exists())$slug=$base.'-'.$suffix++;return $slug;}
 private function createDefaultSite(Tenant $tenant):void{
  $home=$tenant->pages()->create(['slug'=>'home','title'=>'Home','status'=>'published','is_homepage'=>true,'published_at'=>now()]);
  $home->sections()->createMany([
   ['type'=>'hero','name'=>'Homepage Hero','sort_order'=>10,'is_enabled'=>true,'content'=>['eyebrow'=>'WELCOME','heading'=>$tenant->name,'body'=>'Discover upcoming social events, membership information, and everything happening in our community.','primary_label'=>'View Events','primary_url'=>'/events'],'settings'=>['alignment'=>'left']],
   ['type'=>'event_grid','name'=>'Upcoming Events','sort_order'=>20,'is_enabled'=>true,'content'=>['heading'=>'Upcoming Events','limit'=>6],'settings'=>[]],
   ['type'=>'cta','name'=>'Member CTA','sort_order'=>30,'is_enabled'=>true,'content'=>['heading'=>'Join the community','body'=>'Create an adult member profile to RSVP, save events, and manage your attendance.','button_label'=>'Create Account','button_url'=>'/register'],'settings'=>[]],
  ]);
  $about=$tenant->pages()->create(['slug'=>'about','title'=>'About','status'=>'published','is_homepage'=>false,'published_at'=>now()]);
  $about->sections()->create(['type'=>'rich_text','name'=>'About Us','sort_order'=>10,'is_enabled'=>true,'content'=>['heading'=>'About '.$tenant->name,'body'=>'Use the Website Builder to replace this text with your club, organizer, venue, or private-host information.'],'settings'=>[]]);
  foreach([['header','Events','/events',10],['header','About','/about',20],['footer','Events','/events',10],['footer','About','/about',20]] as [$location,$label,$url,$sort])CmsNavigationItem::create(['tenant_id'=>$tenant->id,'location'=>$location,'label'=>$label,'url'=>$url,'sort_order'=>$sort,'is_enabled'=>true]);
  foreach(['tagline'=>'Private social events and community experiences.','footer_text'=>'Events, RSVPs and member access managed securely through this hosted website.'] as $key=>$value)SiteSetting::create(['tenant_id'=>$tenant->id,'group'=>'general','key'=>$key,'value'=>$value,'type'=>'string','is_public'=>true]);
 }
}