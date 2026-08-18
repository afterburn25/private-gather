<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('events',function(Blueprint $t){$t->string('category')->nullable()->index();$t->string('dress_code')->nullable();$t->longText('rules')->nullable();$t->json('schedule')->nullable();$t->boolean('waitlist_enabled')->default(true);$t->boolean('requires_verified_profile')->default(false);$t->timestamp('registration_opens_at')->nullable();$t->timestamp('registration_closes_at')->nullable();});
  Schema::create('event_questions',function(Blueprint $t){$t->id();$t->foreignId('event_id')->constrained()->cascadeOnDelete();$t->string('label');$t->string('type')->default('text');$t->json('options')->nullable();$t->boolean('required')->default(false);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();});
  Schema::create('event_waitlist',function(Blueprint $t){$t->id();$t->foreignId('event_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->unsignedSmallInteger('guest_count')->default(1);$t->unsignedInteger('position')->default(0);$t->timestamps();$t->unique(['event_id','user_id']);});
  Schema::create('event_favorites',function(Blueprint $t){$t->id();$t->foreignId('event_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->timestamps();$t->unique(['event_id','user_id']);});
  Schema::create('tenant_follows',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->timestamps();$t->unique(['tenant_id','user_id']);});
 }
 public function down():void{Schema::dropIfExists('tenant_follows');Schema::dropIfExists('event_favorites');Schema::dropIfExists('event_waitlist');Schema::dropIfExists('event_questions');Schema::table('events',fn(Blueprint $t)=>$t->dropColumn(['category','dress_code','rules','schedule','waitlist_enabled','requires_verified_profile','registration_opens_at','registration_closes_at']));}
};
