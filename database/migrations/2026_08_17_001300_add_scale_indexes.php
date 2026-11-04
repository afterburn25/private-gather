<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('events',function(Blueprint $t){$t->index(['tenant_id','status','starts_at'],'events_tenant_status_starts_idx');$t->index(['visibility','status','starts_at'],'events_marketplace_idx');});
  Schema::table('event_rsvps',function(Blueprint $t){$t->index(['event_id','status','created_at'],'rsvps_event_status_created_idx');$t->index(['user_id','status'],'rsvps_user_status_idx');});
  Schema::table('orders',function(Blueprint $t){$t->index(['tenant_id','status','created_at'],'orders_tenant_status_created_idx');});
  Schema::table('messages',function(Blueprint $t){$t->index(['user_id','created_at'],'messages_user_created_idx');});
 }
 public function down():void{
  Schema::table('messages',fn(Blueprint $t)=>$t->dropIndex('messages_user_created_idx'));Schema::table('orders',fn(Blueprint $t)=>$t->dropIndex('orders_tenant_status_created_idx'));Schema::table('event_rsvps',function(Blueprint $t){$t->dropIndex('rsvps_event_status_created_idx');$t->dropIndex('rsvps_user_status_idx');});Schema::table('events',function(Blueprint $t){$t->dropIndex('events_tenant_status_starts_idx');$t->dropIndex('events_marketplace_idx');});
 }
};