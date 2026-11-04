<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{Schema::table('tenant_domains',function(Blueprint $t){$t->string('dns_status')->default('pending')->index();$t->timestamp('dns_last_checked_at')->nullable();$t->text('last_error')->nullable();$t->json('health')->nullable();});}
 public function down():void{Schema::table('tenant_domains',fn(Blueprint $t)=>$t->dropColumn(['dns_status','dns_last_checked_at','last_error','health']));}
};