<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('events',function(Blueprint $t){$t->string('recurrence_rule',20)->nullable();$t->timestamp('recurrence_until')->nullable();$t->foreignId('parent_event_id')->nullable()->constrained('events')->nullOnDelete();});
  $now=now();$plans=[
   ['code'=>'starter','name'=>'Starter','price_monthly_cents'=>0,'features'=>['custom_domains'=>false,'white_label'=>false,'staff_limit'=>2,'events_limit'=>25,'analytics'=>false,'ticketing'=>true]],
   ['code'=>'pro','name'=>'Pro','price_monthly_cents'=>0,'features'=>['custom_domains'=>true,'white_label'=>false,'staff_limit'=>10,'events_limit'=>'unlimited','analytics'=>true,'ticketing'=>true]],
   ['code'=>'business','name'=>'Business','price_monthly_cents'=>0,'features'=>['custom_domains'=>true,'white_label'=>false,'staff_limit'=>30,'events_limit'=>'unlimited','analytics'=>true,'ticketing'=>true,'priority_support'=>true]],
   ['code'=>'white-label','name'=>'White Label','price_monthly_cents'=>0,'features'=>['custom_domains'=>true,'white_label'=>true,'staff_limit'=>'unlimited','events_limit'=>'unlimited','analytics'=>true,'ticketing'=>true,'priority_support'=>true]],
  ];
  foreach($plans as $i=>$p)DB::table('plans')->updateOrInsert(['code'=>$p['code']],['name'=>$p['name'],'price_monthly_cents'=>$p['price_monthly_cents'],'currency'=>'USD','features'=>json_encode($p['features']),'active'=>1,'sort_order'=>$i*10,'created_at'=>$now,'updated_at'=>$now]);
  $starter=DB::table('plans')->where('code','starter')->value('id');foreach(DB::table('tenants')->select(['id','type','settings'])->get() as $tenant){$settings=json_decode((string)$tenant->settings,true);if(!is_array($settings))$settings=[];if(!array_key_exists('marketplace_enabled',$settings)){$settings['marketplace_enabled']=$tenant->type!=='private_host';DB::table('tenants')->where('id',$tenant->id)->update(['settings'=>json_encode($settings)]);}DB::table('tenant_subscriptions')->updateOrInsert(['tenant_id'=>$tenant->id],['plan_id'=>$starter,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);}
 }
 public function down():void{Schema::table('events',function(Blueprint $t){$t->dropConstrainedForeignId('parent_event_id');$t->dropColumn(['recurrence_rule','recurrence_until']);});}
};