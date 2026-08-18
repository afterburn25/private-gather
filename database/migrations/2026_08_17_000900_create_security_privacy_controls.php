<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('users',function(Blueprint $t){$t->text('two_factor_secret')->nullable();$t->text('two_factor_recovery_codes')->nullable();$t->timestamp('two_factor_confirmed_at')->nullable();$t->timestamp('privacy_accepted_at')->nullable();$t->string('privacy_version',50)->nullable();});
  Schema::create('consent_records',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('consent_type')->index();$t->string('document_version',80);$t->boolean('granted');$t->string('ip_hash',64)->nullable();$t->timestamp('recorded_at');$t->timestamps();$t->index(['user_id','consent_type','recorded_at']);});
  Schema::create('data_requests',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('type')->index();$t->string('status')->default('pending')->index();$t->timestamp('requested_at');$t->timestamp('completed_at')->nullable();$t->string('artifact_path')->nullable();$t->text('admin_notes')->nullable();$t->timestamps();});
  Schema::create('security_events',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('event')->index();$t->string('ip_hash',64)->nullable();$t->string('user_agent_hash',64)->nullable();$t->json('metadata')->nullable();$t->timestamp('occurred_at')->index();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('security_events');Schema::dropIfExists('data_requests');Schema::dropIfExists('consent_records');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['two_factor_secret','two_factor_recovery_codes','two_factor_confirmed_at','privacy_accepted_at','privacy_version']));}
};