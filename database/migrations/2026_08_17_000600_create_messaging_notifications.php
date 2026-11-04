<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('conversations',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();$t->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();$t->string('type')->default('direct')->index();$t->string('subject')->nullable();$t->timestamps();});
  Schema::create('conversation_participants',function(Blueprint $t){$t->id();$t->foreignId('conversation_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->timestamp('last_read_at')->nullable();$t->boolean('muted')->default(false);$t->timestamps();$t->unique(['conversation_id','user_id']);});
  Schema::create('messages',function(Blueprint $t){$t->id();$t->foreignId('conversation_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->longText('body');$t->string('status')->default('sent');$t->timestamps();$t->index(['conversation_id','created_at']);});
  Schema::create('notification_preferences',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->boolean('email_events')->default(true);$t->boolean('email_messages')->default(true);$t->boolean('email_marketing')->default(false);$t->boolean('browser_notifications')->default(true);$t->timestamps();});
  Schema::create('platform_notifications',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('type');$t->json('data');$t->timestamp('read_at')->nullable();$t->timestamps();$t->index(['user_id','read_at']);});
 }
 public function down():void{Schema::dropIfExists('platform_notifications');Schema::dropIfExists('notification_preferences');Schema::dropIfExists('messages');Schema::dropIfExists('conversation_participants');Schema::dropIfExists('conversations');}
};
