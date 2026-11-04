<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users', function(Blueprint $t){$t->timestamp('adult_confirmed_at')->nullable()->after('date_of_birth');$t->timestamp('terms_accepted_at')->nullable();$t->timestamp('last_login_at')->nullable();$t->string('locale',10)->default('en');});
  Schema::create('profiles', function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->foreignId('partner_user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('profile_type')->default('individual')->index();$t->string('headline')->nullable();$t->longText('bio')->nullable();$t->string('city')->nullable()->index();$t->string('region')->nullable()->index();$t->string('avatar_path')->nullable();$t->string('cover_path')->nullable();$t->json('interests')->nullable();$t->json('visibility')->nullable();$t->boolean('discoverable')->default(true)->index();$t->timestamps();});
  Schema::create('profile_photos', function(Blueprint $t){$t->id();$t->foreignId('profile_id')->constrained()->cascadeOnDelete();$t->string('path');$t->string('visibility')->default('members')->index();$t->unsignedInteger('sort_order')->default(0);$t->boolean('is_primary')->default(false);$t->timestamps();});
  Schema::create('user_blocks', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();$t->timestamps();$t->unique(['user_id','blocked_user_id']);});
 }
 public function down(): void { Schema::dropIfExists('user_blocks'); Schema::dropIfExists('profile_photos'); Schema::dropIfExists('profiles'); Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['adult_confirmed_at','terms_accepted_at','last_login_at','locale'])); }
};
