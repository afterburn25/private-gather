<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('tenant_branding',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();$t->string('logo_path')->nullable();$t->string('favicon_path')->nullable();$t->string('primary_color',20)->nullable();$t->string('accent_color',20)->nullable();$t->string('font_family',120)->nullable();$t->string('email_from_name')->nullable();$t->boolean('show_platform_branding')->default(true);$t->json('theme')->nullable();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('tenant_branding');}
};